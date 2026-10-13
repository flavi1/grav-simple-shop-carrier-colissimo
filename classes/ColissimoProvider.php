<?php

namespace Grav\Plugin\SimpleShopCarrierColissimo;

use Grav\Plugin\SimpleShop\Delivery\CarrierProviderInterface;
use Grav\Plugin\SimpleShop\Delivery\CarrierUnavailable;
use Symfony\Component\Yaml\Yaml;

/**
 * Fournisseur Colissimo : décrit ses modes (grille du contrat, modifiable ensuite dans l'administration)
 * et cherche les points de retrait. Les prix, la marge, la TVA et le plancher légal sont gérés par le cœur.
 */
class ColissimoProvider implements CarrierProviderInterface
{
    /** @var array */
    private $config;

    /** @var string */
    private $gridFile;

    /** @var object|null Journal Grav (méthodes error/warning), facultatif. */
    private $log;

    /** @var PickupPointClient */
    private $client;

    /** @var array|null */
    private $grid = null;

    public function __construct(array $config, string $gridFile, $log = null, ?PickupPointClient $client = null)
    {
        $this->config = $config;
        $this->gridFile = $gridFile;
        $this->log = $log;
        $this->client = $client ?? new PickupPointClient($config);
    }

    public function getId(): string
    {
        return 'colissimo';
    }

    public function getLabel(): string
    {
        return 'Colissimo';
    }

    public function getModes(): array
    {
        $grid = $this->grid();
        $pickupReady = !empty($this->config['pickup_enabled']) && $this->client->isConfigured();

        $modes = [];
        foreach ((array) ($grid['modes'] ?? []) as $local => $def) {
            $rules = [];
            $lo = 0;
            foreach ((array) ($def['bands'] ?? []) as $band) {
                $hi = (int) $band[0];
                $rules[] = ['zone' => (string) ($grid['zone'] ?? 'FR-METRO'), 'min_weight' => $lo, 'max_weight' => $hi, 'price' => (float) $band[1]];
                $lo = $hi + 1;
            }

            $needsPoint = !empty($def['requires_pickup_point']);
            $available = true;
            $reason = '';
            if ($needsPoint && !$pickupReady) {
                $available = false;
                $reason = empty($this->config['pickup_enabled'])
                    ? "Points de retrait désactivés dans les réglages du plugin Colissimo."
                    : "Identifiants du service web Colissimo non renseignés (réglages du plugin Colissimo).";
            }

            $modes[(string) $local] = [
                'name'                  => (string) $def['name'],
                'description'           => (string) ($def['description'] ?? ''),
                'enabled'               => true,
                'constraints'           => ['max_weight' => (int) ($def['max_weight'] ?? 0) ?: null, 'max_thickness' => null],
                'rules'                 => $rules,
                'requires_pickup_point' => $needsPoint,
                'available'             => $available,
                'unavailable_reason'    => $reason,
            ];
        }

        return $modes;
    }

    public function findPickupPoints(string $modeKey, array $address, array $metrics): array
    {
        $def = $this->grid()['modes'][$modeKey] ?? null;
        if ($def === null || empty($def['requires_pickup_point'])) {
            throw new CarrierUnavailable("Mode Colissimo sans point de retrait : {$modeKey}");
        }
        if (empty($this->config['pickup_enabled'])) {
            throw new CarrierUnavailable('Points de retrait désactivés.');
        }

        return $this->client->findPickupPoints($address, (int) ($metrics['weight'] ?? 0));
    }

    public function testConnection(): array
    {
        return $this->client->testConnection();
    }

    /** Informations sur la grille livrée (affichées dans l'administration). */
    public function gridInfo(): array
    {
        $g = $this->grid();

        return ['basis' => (string) ($g['basis'] ?? 'ht'), 'valid_from' => (string) ($g['valid_from'] ?? '')];
    }

    private function grid(): array
    {
        if ($this->grid === null) {
            $this->grid = is_file($this->gridFile) ? (array) Yaml::parseFile($this->gridFile) : [];
        }

        return $this->grid;
    }
}
