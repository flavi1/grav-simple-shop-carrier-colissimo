<?php

namespace Grav\Plugin\SimpleShopCarrierColissimo;

use Grav\Plugin\SimpleShop\Delivery\CarrierUnavailable;

/**
 * Client du service web « Point retrait » de Colissimo (spécification « Web services de livraison »,
 * https://www.colissimo.entreprise.laposte.fr/sites/default/files/2021-04/spec_ws_livraison_en.pdf).
 *
 * Appel HTTPS en GET de `findRDVPointRetraitAcheminement`, réponse XML (enveloppe SOAP). Les paramètres de
 * la requête et les champs de la réponse suivent la spécification ; les URL, noms de champs et exemples de
 * réponses ont été recoupés avec la bibliothèque existante dansmaculotte/colissimo-php (licence MIT).
 *
 * Aucun appel réseau n'est fait ici sans identifiants. Le mot de passe et la clé d'API ne sont jamais journalisés.
 */
class PickupPointClient
{
    public const BASE_URL = 'https://ws.colissimo.fr/pointretrait-ws-cxf/PointRetraitServiceWS/2.0/';

    /** Codes d'erreur qui signifient « aucun point », pas une panne. */
    private const NO_RESULT = [300, 301];

    /** Codes d'erreur d'identifiants ou d'autorisation. */
    private const AUTH_ERRORS = [101, 102, 201, 202];

    private const DAYS = [
        'Lundi' => 'Lun', 'Mardi' => 'Mar', 'Mercredi' => 'Mer', 'Jeudi' => 'Jeu',
        'Vendredi' => 'Ven', 'Samedi' => 'Sam', 'Dimanche' => 'Dim',
    ];

    /** @var array{account_number: string, password: string, api_key: string, timeout: int, max_points: int} */
    private $settings;

    /** @var callable(string): array{status: int, body: string} */
    private $transport;

    /**
     * @param array         $settings  Réglages du plugin (account_number, password, api_key, timeout, max_points).
     * @param callable|null $transport Fonction (string $url, int $timeout): array{status:int, body:string} ; curl par défaut.
     */
    public function __construct(array $settings, ?callable $transport = null)
    {
        $this->settings = [
            'account_number' => trim((string) ($settings['account_number'] ?? '')),
            'password'       => (string) ($settings['password'] ?? ''),
            'api_key'        => trim((string) ($settings['api_key'] ?? '')),
            'timeout'        => max(1, min(15, (int) ($settings['timeout'] ?? 4))),
            'max_points'     => max(1, min(30, (int) ($settings['max_points'] ?? 10))),
        ];
        $this->transport = $transport ?? [self::class, 'curlTransport'];
    }

    /** Des identifiants complets sont-ils renseignés ? */
    public function isConfigured(): bool
    {
        return preg_match('/^[A-Za-z0-9]{32}$/', $this->settings['api_key']) === 1
            || (preg_match('/^[0-9]{6}$/', $this->settings['account_number']) === 1 && $this->settings['password'] !== '');
    }

    /**
     * Construit l'URL de la requête (sans l'envoyer). Un paramètre sans valeur est omis, comme l'exige la spécification.
     *
     * @param array $query zipCode, city, weight (g), shippingDate (JJ/MM/AAAA), address…
     */
    public function buildUrl(string $operation, array $query): string
    {
        $params = $this->settings['api_key'] !== '' && preg_match('/^[A-Za-z0-9]{32}$/', $this->settings['api_key'])
            ? ['apikey' => $this->settings['api_key']]
            : ['accountNumber' => $this->settings['account_number'], 'password' => $this->settings['password']];

        $params += $query;
        $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);

        return self::BASE_URL . $operation . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Points de retrait proches d'une adresse.
     *
     * @param array $address `postal-code`, `city`, `street` (facultatif).
     * @param int   $weight  Poids du colis en grammes (1 à 99999).
     *
     * @return array[] Points normalisés (voir CarrierProviderInterface::findPickupPoints()), au plus `max_points`.
     *
     * @throws CarrierUnavailable
     */
    public function findPickupPoints(array $address, int $weight, ?\DateTimeInterface $shippingDate = null): array
    {
        if (!$this->isConfigured()) {
            throw new CarrierUnavailable('Identifiants Colissimo non renseignés.');
        }

        $zip = (string) ($address['postal-code'] ?? '');
        $city = trim((string) ($address['city'] ?? ''));
        if (!preg_match('/^\d{5}$/', $zip) || $city === '') {
            throw new CarrierUnavailable('Adresse incomplète pour la recherche de points de retrait.');
        }

        $xml = $this->call('findRDVPointRetraitAcheminement', [
            'address'      => mb_substr(trim((string) ($address['street'] ?? '')), 0, 200),
            'zipCode'      => $zip,
            'city'         => mb_substr($city, 0, 50),
            'countryCode'  => 'FR',
            'weight'       => (string) max(1, min(99999, $weight)),
            'shippingDate' => self::shippingDate($shippingDate)->format('d/m/Y'),
            'filterRelay'  => '1',
            'lang'         => 'FR',
        ]);

        $return = $xml->xpath('//return');
        $code = $return ? (int) $return[0]->errorCode : 1000;
        if ($code !== 0) {
            if (in_array($code, self::NO_RESULT, true)) {
                return [];
            }
            throw new CarrierUnavailable($this->errorMessage($code, $return ? (string) $return[0]->errorMessage : ''));
        }

        $points = [];
        foreach ($xml->xpath('//listePointRetraitAcheminement') as $raw) {
            $point = self::normalizePoint($raw);
            if ($point === null) {
                continue;
            }
            // Le colis doit passer : poids maximal du point (grammes ; 0 = non renseigné).
            if ($point['max_weight_g'] > 0 && $weight > $point['max_weight_g']) {
                continue;
            }
            $points[] = $point;
        }

        usort($points, static fn ($a, $b) => $a['distance_m'] <=> $b['distance_m']);

        return array_slice($points, 0, $this->settings['max_points']);
    }

    /**
     * Contrôle de connexion : une recherche réelle près de Paris.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => "Identifiants incomplets : renseignez le numéro de compte et le mot de passe, ou la clé d'API."];
        }

        try {
            $points = $this->findPickupPoints(['postal-code' => '75001', 'city' => 'Paris'], 500);
        } catch (CarrierUnavailable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Connexion valide (' . count($points) . ' point(s) trouvé(s) près de Paris 1er).'];
    }

    /** Date d'expédition estimée : aujourd'hui (ou le prochain jour ouvré si dimanche), jamais dans le passé. */
    public static function shippingDate(?\DateTimeInterface $now = null): \DateTimeImmutable
    {
        $d = $now ? \DateTimeImmutable::createFromInterface($now) : new \DateTimeImmutable('today');
        while ((int) $d->format('N') === 7) {
            $d = $d->modify('+1 day');
        }

        return $d;
    }

    /**
     * Point normalisé à partir d'un élément XML, ou null s'il est inutilisable (fermé, sans identifiant).
     *
     * @return array|null
     */
    public static function normalizePoint(\SimpleXMLElement $raw): ?array
    {
        $id = trim((string) $raw->identifiant);
        if ($id === '' || strtolower((string) $raw->congesTotal) === 'true') {
            return null;
        }

        $street = trim(implode(' ', array_filter([trim((string) $raw->adresse1), trim((string) $raw->adresse2)])));

        $hours = [];
        foreach (self::DAYS as $long => $short) {
            $ranges = array_filter(
                preg_split('/\s+/', trim((string) $raw->{'horairesOuverture' . $long})) ?: [],
                static fn ($r) => $r !== '' && $r !== '00:00-00:00'
            );
            $hours[$short] = $ranges ? implode(' / ', array_map(static fn ($r) => str_replace('-', '–', $r), $ranges)) : 'fermé';
        }

        // poidsMaxi : la spécification parle de kilogrammes, les réponses réelles sont en grammes (20000).
        $max = (int) $raw->poidsMaxi;
        if ($max > 0 && $max < 1000) {
            $max *= 1000;
        }

        return [
            'id'           => $id,
            'name'         => trim((string) $raw->nom),
            'street'       => $street,
            'postal-code'  => trim((string) $raw->codePostal),
            'city'         => trim((string) $raw->localite),
            'distance_m'   => (int) $raw->distanceEnMetre,
            'hours'        => $hours,
            'network'      => trim((string) $raw->reseau),
            'type'         => trim((string) $raw->typeDePoint),
            'latitude'     => (float) $raw->coordGeolocalisationLatitude,
            'longitude'    => (float) $raw->coordGeolocalisationLongitude,
            'max_weight_g' => $max,
            'accessible'   => strtolower((string) $raw->accesPersonneMobiliteReduite) === 'true',
        ];
    }

    private function call(string $operation, array $query): \SimpleXMLElement
    {
        $url = $this->buildUrl($operation, $query);

        try {
            $res = ($this->transport)($url, $this->settings['timeout']);
        } catch (\Throwable $e) {
            throw new CarrierUnavailable('Service Colissimo injoignable : ' . $e->getMessage());
        }

        if (($res['status'] ?? 0) !== 200 || !is_string($res['body'] ?? null) || $res['body'] === '') {
            throw new CarrierUnavailable('Service Colissimo : réponse HTTP ' . (int) ($res['status'] ?? 0) . '.');
        }

        $prev = libxml_use_internal_errors(true);
        try {
            // Pas de chargement d'entités externes (XXE) : LIBXML_NONET, et PHP ≥ 8 ne résout plus les entités par défaut.
            $xml = simplexml_load_string($res['body'], \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
        if ($xml === false) {
            throw new CarrierUnavailable('Service Colissimo : réponse illisible.');
        }

        return $xml;
    }

    private function errorMessage(int $code, string $fromService): string
    {
        $known = [
            101 => 'numéro de compte absent', 102 => 'mot de passe absent',
            104 => 'code postal absent', 105 => 'ville absente', 106 => "date d'expédition absente",
            120 => 'poids invalide', 121 => 'poids hors limites (1 à 99999 g)', 122 => 'date invalide',
            125 => 'code postal incorrect', 129 => 'adresse incorrecte',
            201 => 'identifiant ou mot de passe invalide', 202 => 'service non autorisé pour ce compte',
            1000 => 'erreur système du service Colissimo',
        ];
        $text = $known[$code] ?? ($fromService !== '' ? $fromService : 'erreur inconnue');

        return in_array($code, self::AUTH_ERRORS, true)
            ? "Colissimo a refusé les identifiants ({$code} : {$text})."
            : "Colissimo : {$code} : {$text}.";
    }

    /** Transport par défaut : curl, TLS vérifié, redirections refusées. */
    public static function curlTransport(string $url, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER     => ['Accept: text/xml, application/xml'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            // Le message de curl contient parfois l'URL (donc le mot de passe) : on ne garde que la nature de l'erreur.
            throw new \RuntimeException('erreur réseau (' . preg_replace('#https?://\S+#', '[url]', $err) . ')');
        }

        return ['status' => $status, 'body' => (string) $body];
    }
}
