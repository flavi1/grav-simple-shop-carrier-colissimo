<?php

namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Config\Config;
use Grav\Plugin\SimpleShopCarrierColissimo\ColissimoProvider;
use RocketTheme\Toolbox\Event\Event;

/**
 * Transporteur Colissimo pour Simple Shop (voir classes/ColissimoProvider.php et le contrat
 * `Grav\Plugin\SimpleShop\Delivery\CarrierProviderInterface`).
 */
class SimpleShopCarrierColissimoPlugin extends Plugin
{
    /**
     * L'autoloader est déclaré dès le constructeur : l'abonnement ci-dessous est statique, donc actif avant
     * `onPluginsInitialized` (même mécanique que le plugin de paiement Stripe), y compris pour l'API d'administration.
     */
    public function __construct($name, Grav $grav, ?Config $config = null)
    {
        parent::__construct($name, $grav, $config);
        $grav['loader']->addPsr4('Grav\\Plugin\\SimpleShopCarrierColissimo\\', __DIR__ . '/classes/');
    }

    public static function getSubscribedEvents()
    {
        return [
            'onSimpleShopRegisterCarriers' => ['onSimpleShopRegisterCarriers', 0],
        ];
    }

    public function onSimpleShopRegisterCarriers(Event $event): void
    {
        $config = (array) $this->config->get('plugins.simple-shop-carrier-colissimo', []);
        if (empty($config['enabled'])) {
            return;
        }

        $carriers = $event['carriers'];
        $carriers['colissimo'] = new ColissimoProvider($config, __DIR__ . '/data/grid-facilite-2025.yaml', $this->grav['log'] ?? null);
        $event['carriers'] = $carriers;
    }
}
