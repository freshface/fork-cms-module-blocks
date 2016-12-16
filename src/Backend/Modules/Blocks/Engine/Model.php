<?php

namespace Backend\Modules\Blocks\Engine;

use Backend\Core\Engine\Model as BackendModel;
use Backend\Core\Engine\Language;
use Backend\Modules\Pages\Engine\Model as BackendPagesModel;

use Symfony\Component\Finder\Finder;

/**
 * In this file we store all generic functions that we will be using in the Blocks module
 *
 * @author Frederik Heyninck <frederik@figure8.be>
 */
class Model
{
    const QRY_DATAGRID_BROWSE =
        'SELECT i.id, c.name,  i.sequence, i.hidden
         FROM blocks AS i
         INNER JOIN block_content as c  on i.id = c.block_id
         WHERE c.language = ? AND i.status = ? ORDER BY sequence DESC';

       /**
       * Get the maximum Team sequence.
       *
       * @return int
       */
      public static function getMaximumSequence()
      {
          return (int) BackendModel::get('database')->getVar(
              'SELECT MAX(i.sequence)
               FROM blocks AS i'
          );
      }

    /**
     * Delete a certain item
     *
     * @param int $id
     */
    public static function delete($id)
    {
        BackendModel::get('database')->delete('blocks', 'id = ?', (int) $id);
        BackendModel::get('database')->delete('block_content', 'block_id = ?', (int) $id);
    }

    /**
     * Checks if a certain item exists
     *
     * @param int $id
     * @return bool
     */
    public static function exists($id)
    {
        return (bool) BackendModel::get('database')->getVar(
            'SELECT 1
             FROM blocks AS i
             WHERE i.id = ?
             LIMIT 1',
            array((int) $id)
        );
    }

    /**
     * Fetches a certain item
     *
     * @param int $id
     * @return array
     */
    public static function get($id)
    {
        $db = BackendModel::get('database');

        $return =  (array) $db->getRecord(
            'SELECT i.*, UNIX_TIMESTAMP(i.publish_on) as publish_on
             FROM blocks AS i
             WHERE i.id = ?',
            array((int) $id)
        );

        // data found
        $return['content'] = (array) $db->getRecords(
            'SELECT i.* FROM block_content AS i
            WHERE i.block_id = ?',
            array((int) $id), 'language');

        return  $return;
    }


    /**
     * Insert an item in the database
     *
     * @param array $item
     * @return int
     */
    public static function insert(array $item)
    {
        $item['created_on'] = BackendModel::getUTCDate();
        $item['edited_on'] = BackendModel::getUTCDate();

        return (int) BackendModel::get('database')->insert('blocks', $item);
    }

    public static function insertContent(array $content)
    {
        foreach ($content as &$item) {
            $data = [
                    'id' => $item['block_id'],
                    'language' => $item['language'],
                    'extra_label' => $item['name'],
                    'edit_url' => BackendModel::createURLForAction('Edit') . '&id=' . $item['block_id'],
                ];

            $item['extra_id'] = BackendModel::insertExtra(
                    'widget',
                    'Blocks',
                    'Block',
                    'Block',
                    $data
                );


            BackendModel::get('database')->insert('block_content', $item);
        }
    }

    /**
     * Updates an item
     *
     * @param array $item
     */
    public static function update(array $item)
    {
        $item['edited_on'] = BackendModel::getUTCDate();

        BackendModel::get('database')->update(
            'blocks', $item, 'id = ?', (int) $item['id']
        );
    }

    public static function updateContent(array $content, $id)
    {
        $db = BackendModel::get('database');
        foreach ($content as $language => $row) {
            $data = [
                    'id' => $row['block_id'],
                    'language' => $row['language'],
                    'extra_label' => $row['name'],
                ];

            BackendModel::updateExtra($row['extra_id'], 'data', $data);


            $db->update('block_content', $row, 'block_id = ? AND language = ?', array($id, $language));
        }
    }

    public static function getPagesForDropdown($language = null)
    {
        // redefine
        $language = ($language !== null) ? (string) $language : Language::getWorkingLanguage();

        // get tree
        $levels = BackendPagesModel::getTree(array(0), null, 1, $language);

        // init var
        $titles = array();
        $sequences = array();
        $keys = array();
        $return = array();

        // loop levels
        foreach ($levels as $pages) {
            // loop all items on this level
            foreach ($pages as $pageID => $page) {
                // init var
                $parentID = (int) $page['parent_id'];

                // get URL for parent
                $URL = (isset($keys[$parentID])) ? $keys[$parentID] : '';

                // add it
                $keys[$pageID] = trim($URL . '/' . $page['url'], '/');

                // add to sequences
                if ($page['type'] == 'footer') {
                    $sequences['footer'][(string) trim(
                        $URL . '/' . $page['url'],
                        '/'
                    )] = $pageID;
                } else {
                    $sequences['pages'][(string) trim($URL . '/' . $page['url'], '/')] = $pageID;
                }

                // get URL for parent
                $title = (isset($titles[$parentID])) ? $titles[$parentID] : '';
                $title = trim($title, \SpoonFilter::ucfirst(Language::lbl('Home')) . ' > ');

                // add it
                $titles[$pageID] = trim($title . ' > ' . $page['title'], ' > ');
            }
        }

        if (isset($sequences['pages'])) {
            // sort the sequences
            ksort($sequences['pages']);

            // loop to add the titles in the correct order
            foreach ($sequences['pages'] as $id) {
                if (isset($titles[$id])) {
                    $return[ self::getFullURL($id, $language) ] = $titles[$id];
                }
            }
        }

        if (isset($sequences['footer'])) {
            foreach ($sequences['footer'] as $id) {
                if (isset($titles[$id])) {
                    $return[  self::getFullURL($id, $language) ] = $titles[$id];
                }
            }
        }

        // return
        return $return;
    }

    public static function getFullURL($id, $language)
    {
        $keys = BackendPagesModel::getCacheBuilder()->getKeys($language);
        $hasMultiLanguages = BackendModel::getContainer()->getParameter('site.multilanguage');

        // available in generated file?
        if (isset($keys[$id])) {
            $url = $keys[$id];
        } elseif ($id == 0) {
            // parent id 0 hasn't an url
            $url = '/';

            // multilanguages?
            if ($hasMultiLanguages) {
                $url = '/' . $language;
            }

            // return the unique URL!
            return $url;
        } else {
            // not available
            return false;
        }

        // if the is available in multiple languages we should add the current lang
        if ($hasMultiLanguages) {
            $url = '/' . $language . '/' . $url;
        } else {
            // just prepend with slash
            $url = '/' . $url;
        }

        // return the unique URL!
        return urldecode($url);
    }

    /**
     * Get templates.
     *
     * @return array
     */
    public static function getTemplates()
    {
        $templates = array();
        $finder = new Finder();
        $finder->name('*.html.twig');
        $finder->in(FRONTEND_MODULES_PATH . '/Blocks/Layout/Widgets');

        // if there is a custom theme we should include the templates there also
        $theme = BackendModel::get('fork.settings')->get('Core', 'theme', 'core');
        if ($theme != 'core') {
            $path = FRONTEND_PATH . '/Themes/' . $theme . '/Modules/Blocks/Layout/Widgets';
            if (is_dir($path)) {
                $finder->in($path);
            }
        }

        foreach ($finder->files() as $file) {
            $templates[] = $file->getBasename();
        }

        return array_unique($templates);
    }
}
