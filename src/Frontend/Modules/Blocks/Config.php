<?php

namespace Frontend\Modules\Blocks;

use Frontend\Core\Engine\Base\Config as BaseConfig;

/**
 * This is the configuration-object for the Shop Base module
 *
 * @author Frederik Heyninck <frederik@figure8.be>
 */
final class Config extends BaseConfig
{
    /**
     * The default action
     *
     * @var string
     */
    protected $defaultAction = 'Index';

    /**
     * The disabled actions
     *
     * @var array
     */
    protected $disabledActions = array();
}
