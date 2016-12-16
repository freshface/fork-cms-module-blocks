<?php

namespace Frontend\Modules\Blocks\Widgets;

use Frontend\Core\Engine\Base\Widget as FrontendBaseWidget;
use Frontend\Core\Engine\Exception as FrontendException;
use Frontend\Core\Engine\Theme as FrontendTheme;
use Frontend\Modules\Blocks\Engine\Model as FrontendBlocksModel;

class Block extends FrontendBaseWidget
{
    /**
     * The item.
     *
     * @var    array
     */
    private $item;

    /**
     * Assign the template path
     *
     * @return string
     */
    private function assignTemplate()
    {
        $template = FrontendTheme::getPath(FRONTEND_MODULES_PATH . '/Blocks/Layout/Widgets/Default.html.twig');

        // is the content block visible?
        if (!empty($this->item)) {
            // check if the given template exists
            try {
                $template = FrontendTheme::getPath(
                    FRONTEND_MODULES_PATH . '/Blocks/Layout/Widgets/' . $this->item['template']
                );
            } catch (FrontendException $e) {
                // do nothing
            }
        } else {
            // set a default description so we don't see the template data
            $this->item['description'] = '';
        }

        return $template;
    }

    /**
     * Execute the extra
     */
    public function execute()
    {
        parent::execute();
        $this->loadData();
        $template = $this->assignTemplate();
        $this->loadTemplate($template);
        $this->parse();
    }

    /**
     * Load the data
     */
    private function loadData()
    {
        $this->item = FrontendBlocksModel::get((int) $this->data['id']);
    }

    /**
     * Parse into template
     */
    private function parse()
    {
        // assign data
        $this->tpl->assign('widgetBlocks', $this->item);
    }
}
