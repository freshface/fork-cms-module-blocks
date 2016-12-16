<?php

namespace Backend\Modules\Blocks\Actions;

use Backend\Core\Engine\Base\ActionIndex;
use Backend\Core\Engine\Authentication;
use Backend\Core\Engine\DataGridDB;
use Backend\Core\Language\Language;
use Backend\Core\Engine\Model;
use Backend\Modules\Blocks\Engine\Model as BackendBlocksModel;
use Backend\Core\Engine\Form;

/**
 * This is the index-action (default), it will display the overview of Blocks posts
 *
 * @author Frederik Heyninck <frederik@figure8.be>
 */
class Index extends ActionIndex
{
    private $filter = [];

    /**
     * Execute the action
     */
    public function execute()
    {
        parent::execute();

        $this->setFilter();
        $this->loadForm();

        $this->loadDataGridBlocks();
        $this->loadDataGridBlocksDrafts();
        $this->parse();
        $this->display();
    }

    /**
     * Load the dataGrid
     */
    protected function loadDataGridBlocks()
    {
        $query = 'SELECT i.id, c.name, i.hidden
         FROM blocks AS i
         INNER JOIN block_content as c  on i.id = c.block_id';


        $query .= ' WHERE 1';

        $parameters = array();
        $query .= ' AND c.language = ?';
        $parameters[] = Language::getWorkingLanguage();

        $query .= ' AND i.status = ?';
        $parameters[] = 'active';

        if ($this->filter['value']) {
            $query .= ' AND c.name LIKE ?';
            $parameters[] = '%' . $this->filter['value'] . '%';
        }


        $query .= 'GROUP BY i.id ORDER BY sequence DESC';

        $this->dataGridBlocks = new DataGridDB(
            $query,
            $parameters
        );

        $this->dataGridBlocks->setPagingLimit(50);
        $this->dataGridBlocks->setURL($this->dataGridBlocks->getURL() . '&' . http_build_query($this->filter));

        //$this->dataGridBlocks->enableSequenceByDragAndDrop();

        $this->dataGridBlocks->setColumnAttributes(
            'name', array('class' => 'title')
        );

        // check if this action is allowed
        if (Authentication::isAllowedAction('Edit')) {
            $this->dataGridBlocks->addColumn(
                'edit', null, Language::lbl('Edit'),
                Model::createURLForAction('Edit') . '&amp;id=[id]',
                Language::lbl('Edit')
            );
            $this->dataGridBlocks->setColumnURL(
                'name', Model::createURLForAction('Edit') . '&amp;id=[id]'
            );
        }
    }

    /**
     * Load the dataGrid
     */
    protected function loadDataGridBlocksDrafts()
    {
        $query = 'SELECT i.id, c.name,  i.hidden
         FROM blocks AS i
         INNER JOIN block_content as c  on i.id = c.block_id';

        $query .= ' WHERE 1';

        $parameters = array();
        $query .= ' AND c.language = ?';
        $parameters[] = Language::getWorkingLanguage();

        $query .= ' AND i.status = ?';
        $parameters[] = 'draft';

        if ($this->filter['value']) {
            $query .= ' AND c.name LIKE ?';
            $parameters[] = '%' . $this->filter['value'] . '%';
        }

        $query .= 'GROUP BY i.id ORDER BY sequence DESC';

        $this->dataGridBlocksDrafts = new DataGridDB(
            $query,
            $parameters
        );

        $this->dataGridBlocksDrafts->setPagingLimit(50);
        $this->dataGridBlocksDrafts->setURL($this->dataGridBlocksDrafts->getURL() . '&' . http_build_query($this->filter));

        //$this->dataGridBlocksDrafts->enableSequenceByDragAndDrop();

        $this->dataGridBlocks->setColumnAttributes(
            'name', array('class' => 'title')
        );

        // check if this action is allowed
        if (Authentication::isAllowedAction('Edit')) {
            $this->dataGridBlocksDrafts->addColumn(
                'edit', null, Language::lbl('Edit'),
                Model::createURLForAction('Edit') . '&amp;id=[id]',
                Language::lbl('Edit')
            );
            $this->dataGridBlocksDrafts->setColumnURL(
                'name', Model::createURLForAction('Edit') . '&amp;id=[id]'
            );
        }
    }

    /**
     * Load the form
     */
    private function loadForm()
    {
        $this->frm = new Form('filter', Model::createURLForAction(), 'get');

        $this->frm->addText('value', $this->filter['value']);

        // manually parse fields
        $this->frm->parse($this->tpl);
    }


    /**
     * Sets the filter based on the $_GET array.
     */
    private function setFilter()
    {
        $this->filter['value'] = $this->getParameter('value') == null ? '' : $this->getParameter('value');
    }


    /**
     * Parse the page
     */
    protected function parse()
    {
        // parse the dataGrid if there are results
        $this->tpl->assign('dataGridBlocks', (string) $this->dataGridBlocks->getContent());
        $this->tpl->assign('dataGridBlocksDraft', (string) $this->dataGridBlocksDrafts->getContent());
    }
}
