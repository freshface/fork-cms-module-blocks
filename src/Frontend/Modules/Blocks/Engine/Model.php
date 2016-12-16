<?php

namespace Frontend\Modules\Blocks\Engine;

use Frontend\Core\Engine\Model as FrontendModel;
use Frontend\Core\Engine\Language;
use Frontend\Core\Engine\Navigation;
use Frontend\Modules\Blocks\Engine\Images as FrontendBlocksImagesModel;

/**
 * In this file we store all generic functions that we will be using in the Blocks module
 *
 * @author Frederik Heyninck <frederik@figure8.be>
 */
class Model
{
    public static function get($id)
    {
        $item = (array) FrontendModel::getContainer()->get('database')->getRecord(
           'SELECT i.id, i.image, c.name, c.description, c.link, i.template
            FROM blocks AS i
            JOIN block_content AS c on c.block_id = i.id
            WHERE i.status = ? AND i.publish_on <= ? AND i.id = ? AND c.language = ? AND i.hidden = ?',
           array(
              'active',
              FrontendModel::getUTCDate('Y-m-d H:i') . ':00',
               $id,
               FRONTEND_LANGUAGE,
               'N'
           )
       );

       // no results?
       if (empty($item)) {
           return array();
       }
       // return
       return $item;
    }
}
