<?php
// Tests en ligne de commande : php -d zend.assertions=1 -d assert.exception=1 tests/run.php
// (nécessite symfony/yaml : SS = chemin de simple-shop, YAML = autoload yaml)
require getenv('YAML');
spl_autoload_register(function ($c) {
    foreach (['Grav\\Plugin\\SimpleShop\\' => getenv('SS') . '/classes/', 'Grav\\Plugin\\SimpleShopCarrierColissimo\\' => __DIR__ . '/../classes/'] as $p => $dir) {
        if (strpos($c, $p) === 0) { $f = $dir . str_replace('\\', '/', substr($c, strlen($p))) . '.php'; if (is_file($f)) require $f; }
    }
});
use Grav\Plugin\SimpleShop\Delivery\{CarrierCatalog, CarrierUnavailable, ShippingManager};
use Grav\Plugin\SimpleShopCarrierColissimo\{ColissimoProvider, PickupPointClient};

$fx = fn ($n) => file_get_contents(__DIR__ . "/fixtures/$n");
$seen = [];
$mk = function (string $fixture, array $cfg = []) use ($fx, &$seen) {
    return new PickupPointClient($cfg + ['account_number' => '123456', 'password' => 'p@ss w/rd', 'timeout' => 4, 'max_points' => 10],
        function ($url, $t) use ($fx, $fixture, &$seen) { $seen[] = $url; return ['status' => 200, 'body' => $fx($fixture)]; });
};

// --- URL : paramètres vides omis, mot de passe encodé, clé d'API prioritaire
$c = new PickupPointClient(['account_number' => '123456', 'password' => 'p@ss w/rd']);
$u = $c->buildUrl('findRDVPointRetraitAcheminement', ['zipCode' => '14000', 'city' => 'CAEN', 'address' => '', 'weight' => '340']);
assert(strpos($u, 'https://ws.colissimo.fr/pointretrait-ws-cxf/PointRetraitServiceWS/2.0/findRDVPointRetraitAcheminement?') === 0);
assert(strpos($u, 'accountNumber=123456') !== false && strpos($u, 'password=p%40ss%20w%2Frd') !== false && strpos($u, 'address') === false);
$k = new PickupPointClient(['api_key' => str_repeat('a', 32), 'account_number' => '123456', 'password' => 'x']);
assert(strpos($k->buildUrl('x', ['zipCode' => '14000']), 'apikey=' . str_repeat('a', 32)) !== false && strpos($k->buildUrl('x', []), 'accountNumber') === false);
assert(!(new PickupPointClient([]))->isConfigured() && $c->isConfigured() && $k->isConfigured());
assert(!(new PickupPointClient(['account_number' => '12', 'password' => 'x']))->isConfigured());

// --- Recherche : tri par distance, point fermé et trop léger écartés, horaires lisibles
$pts = $mk('find_ok.xml')->findPickupPoints(['postal-code' => '14000', 'city' => 'CAEN', 'street' => '1 rue X'], 340);
assert(array_column($pts, 'id') === ['149390', '108285', '200002']);        // 200001 fermé (congesTotal) écarté
assert($pts[0]['name'] === 'BUREAU DE POSTE CAEN GAMBETTA' && $pts[0]['distance_m'] === 127 && $pts[0]['network'] === 'R01');
assert($pts[0]['hours']['Lun'] === '09:00–12:00 / 14:00–18:30' && $pts[0]['hours']['Dim'] === '10:00–12:00' && $pts[1]['hours']['Dim'] === 'fermé');
assert($pts[0]['street'] === '2 RUE GEORGES LEBRET' && $pts[0]['postal-code'] === '14000' && $pts[0]['max_weight_g'] === 20000 && is_float($pts[0]['latitude']));
assert(strpos($seen[0], 'weight=340') !== false && strpos($seen[0], 'zipCode=14000') !== false && strpos($seen[0], 'shippingDate=') !== false && strpos($seen[0], 'filterRelay=1') !== false);
assert(preg_match('#shippingDate=\d{2}%2F\d{2}%2F\d{4}#', $seen[0]) === 1);
$heavy = $mk('find_ok.xml')->findPickupPoints(['postal-code' => '14000', 'city' => 'CAEN'], 3000);
assert(array_column($heavy, 'id') === ['149390', '108285']);                // 200002 (2 kg max) écarté
assert(count($mk('find_ok.xml', ['max_points' => 1])->findPickupPoints(['postal-code' => '14000', 'city' => 'CAEN'], 340)) === 1);

// --- Erreurs
assert($mk('find_none.xml')->findPickupPoints(['postal-code' => '14000', 'city' => 'CAEN'], 340) === []);   // 301 : aucun point, pas une panne
try { $mk('find_auth_error.xml')->findPickupPoints(['postal-code' => '14000', 'city' => 'CAEN'], 340); assert(false); }
catch (CarrierUnavailable $e) { assert(strpos($e->getMessage(), '201') !== false && strpos($e->getMessage(), 'p@ss') === false); }
foreach ([['postal-code' => '1400', 'city' => 'CAEN'], ['postal-code' => '14000', 'city' => ''], []] as $bad) {
    try { $mk('find_ok.xml')->findPickupPoints($bad, 340); assert(false); } catch (CarrierUnavailable $e) { assert(true); }
}
foreach ([['status' => 500, 'body' => 'x'], ['status' => 200, 'body' => 'pas du xml'], ['status' => 200, 'body' => '']] as $resp) {
    $cl = new PickupPointClient(['account_number' => '123456', 'password' => 'x'], fn () => $resp);
    try { $cl->findPickupPoints(['postal-code' => '14000', 'city' => 'CAEN'], 340); assert(false); } catch (CarrierUnavailable $e) { assert(true); }
}
$boom = new PickupPointClient(['account_number' => '123456', 'password' => 'secret'], function () { throw new \RuntimeException('timeout'); });
try { $boom->findPickupPoints(['postal-code' => '14000', 'city' => 'CAEN'], 340); assert(false); } catch (CarrierUnavailable $e) { assert(strpos($e->getMessage(), 'secret') === false); }
// XXE : une entité externe n'est jamais lue
$xxe = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><r><return><errorCode>0</errorCode><errorMessage>&x;</errorMessage></return></r>';
$cl = new PickupPointClient(['account_number' => '123456', 'password' => 'x'], fn () => ['status' => 200, 'body' => $xxe]);
try { $cl->findPickupPoints(['postal-code' => '14000', 'city' => 'CAEN'], 340); } catch (CarrierUnavailable $e) {}
// testConnection
assert($mk('find_ok.xml')->testConnection()['ok'] === true && (new PickupPointClient([]))->testConnection()['ok'] === false);
assert($mk('find_auth_error.xml')->testConnection()['ok'] === false);
// date d'expédition : jamais un dimanche
assert(PickupPointClient::shippingDate(new DateTimeImmutable('2026-10-18'))->format('Y-m-d') === '2026-10-19');   // dimanche → lundi
assert(PickupPointClient::shippingDate(new DateTimeImmutable('2026-10-14'))->format('Y-m-d') === '2026-10-14');

// --- Fournisseur et catalogue
$grid = __DIR__ . '/../data/grid-facilite-2025.yaml';
$noKeys = new ColissimoProvider(['enabled' => true], $grid);
$m = $noKeys->getModes();
assert(array_keys($m) === ['domicile', 'retrait'] && $m['domicile']['available'] && !$m['retrait']['available'] && $m['retrait']['unavailable_reason'] !== '');
$ready = new ColissimoProvider(['enabled' => true, 'pickup_enabled' => true, 'account_number' => '123456', 'password' => 'x'], $grid, null, $mk('find_ok.xml'));
$m = $ready->getModes();
assert($m['retrait']['available'] && $m['retrait']['requires_pickup_point'] && $m['domicile']['constraints']['max_weight'] === 30000);
assert(count($m['domicile']['rules']) === 33 && $m['domicile']['rules'][0] === ['zone' => 'FR-METRO', 'min_weight' => 0, 'max_weight' => 250, 'price' => 6.72]
    && $m['domicile']['rules'][1]['min_weight'] === 251 && $m['retrait']['rules'][22]['max_weight'] === 20000 && $m['retrait']['rules'][22]['price'] === 25.51);
assert(count($ready->findPickupPoints('retrait', ['postal-code' => '14000', 'city' => 'CAEN'], ['weight' => 340])) === 3);
try { $ready->findPickupPoints('domicile', ['postal-code' => '14000', 'city' => 'CAEN'], ['weight' => 340]); assert(false); } catch (CarrierUnavailable $e) { assert(true); }

$modes = CarrierCatalog::build(['colissimo' => $ready], []);
$metrics = ['subtotal' => 12.95, 'weight' => 340, 'thickness' => 29];
$q = ShippingManager::quoteMode($modes['colissimo.domicile'], 'FR-METRO', $metrics, []);
assert($q['price'] === 7.99);                                    // 7,57 € HT × 1,055
$q = ShippingManager::quoteMode($modes['colissimo.retrait'], 'FR-METRO', $metrics, []);
assert($q['price'] === 6.28);                                    // 5,95 € HT × 1,055 = 6,277…
assert(ShippingManager::quoteMode(CarrierCatalog::build(['colissimo' => $noKeys], [])['colissimo.retrait'], 'FR-METRO', $metrics, []) === null);   // sans clés : non proposé
assert(ShippingManager::quoteMode($modes['colissimo.domicile'], 'FR-METRO', ['subtotal' => 12.95, 'weight' => 31000, 'thickness' => 1], []) === null);
echo "OK\n";
