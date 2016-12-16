<?php

namespace Backend\Modules\Blocks\Installer;

use Backend\Core\Installer\ModuleInstaller;

/**
 * Installer for the Blocks module
 *
 * @author Frederik Heyninck <frederik@figure8.be>
 */
class Installer extends ModuleInstaller
{
    public function install()
    {
        // import the sql
        $this->importSQL(dirname(__FILE__) . '/Data/install.sql');

        // install the module in the database
        $this->addModule('Blocks');

        // install the locale, this is set here beceause we need the module for this
        $this->importLocale(dirname(__FILE__) . '/Data/locale.xml');

        $this->setModuleRights(1, 'Blocks');

        $this->setActionRights(1, 'Blocks', 'Add');
        $this->setActionRights(1, 'Blocks', 'Delete');
        $this->setActionRights(1, 'Blocks', 'Edit');
        $this->setActionRights(1, 'Blocks', 'Index');


        $this->setActionRights(1, 'Blocks', 'Settings');

        $this->makeSearchable('Blocks');

        // add extra's


        $navigationModulesId = $this->setNavigation(null, 'Modules');
        $this->setNavigation($navigationModulesId, 'Blocks', 'blocks/index', array('blocks/add','blocks/edit', 'blocks/index', 'blocks/add_images', 'blocks/edit_image'), 1);
    }
}
