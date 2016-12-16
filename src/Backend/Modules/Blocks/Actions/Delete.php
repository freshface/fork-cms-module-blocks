<?php

namespace Backend\Modules\Blocks\Actions;

use Backend\Core\Engine\Base\ActionDelete;
use Backend\Core\Engine\Model;
use Backend\Modules\Blocks\Engine\Model as BackendBlocksModel;

/**
 * This is the delete-action, it deletes an item
 *
 * @author Frederik Heyninck <frederik@figure8.be>
 */
class Delete extends ActionDelete
{
    /**
     * Execute the action
     */
    public function execute()
    {
        $this->id = $this->getParameter('id', 'int');

        // does the item exist
        if ($this->id !== null && BackendBlocksModel::exists($this->id)) {
            parent::execute();
            $this->record = (array) BackendBlocksModel::get($this->id);

            // delete extra_ids
            foreach ($this->record['content'] as $row) {
                Model::deleteExtraById($row['extra_id'], true);
            }

            Model::deleteThumbnails(FRONTEND_FILES_PATH . '/' . $this->getModule() . '/image', $this->record['image']);

            BackendBlocksModel::delete($this->id);

            Model::triggerEvent(
                $this->getModule(), 'after_delete',
                array('id' => $this->id)
            );

            $this->redirect(
                Model::createURLForAction('Index') . '&report=deleted'
            );
        } else {
            $this->redirect(Model::createURLForAction('Index') . '&error=non-existing');
        }
    }
}
