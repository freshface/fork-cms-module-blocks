<?php

namespace Backend\Modules\Blocks\Actions;

use Backend\Core\Engine\Base\ActionEdit;
use Backend\Core\Engine\Form;
use Backend\Core\Language\Language;
use Backend\Core\Engine\Model;
use Backend\Modules\Blocks\Engine\Model as BackendBlocksModel;
use Backend\Modules\Blocks\Engine\Images as BackendBlocksImagesModel;

use Backend\Modules\SiteHelpers\Engine\Helper as SiteHelpersHelper;
use Backend\Modules\SiteHelpers\Engine\Model as SiteHelpersModel;
use Backend\Modules\SiteHelpers\Engine\Assets as SiteHelpersAssets;
use Common\Uri as CommonUri;

use Symfony\Component\Filesystem\Filesystem;
use Backend\Core\Engine\Authentication;

/**
 * This is the edit-action, it will display a form with the item data to edit
 *
 * @author Frederik Heyninck <frederik@figure8.be>
 */
class Edit extends ActionEdit
{
    /**
     * Execute the action
     */
    public function execute()
    {
        parent::execute();
        $this->templates = BackendBlocksModel::getTemplates();
        $this->languages = SiteHelpersHelper::getActiveLanguages();

        $this->loadData();
        $this->loadForm();
        $this->validateForm();

        $this->parse();
        $this->display();
    }



    /**
     * Load the item data
     */
    protected function loadData()
    {
        $this->id = $this->getParameter('id', 'int', null);
        if ($this->id == null || !BackendBlocksModel::exists($this->id)) {
            $this->redirect(
                Model::createURLForAction('Index') . '&error=non-existing'
            );
        }

        $this->record = BackendBlocksModel::get($this->id);
    }

    /**
     * Load the form
     */
    protected function loadForm()
    {
        // create form
        $this->frm = new Form('edit');

        $this->frm->addImage('image');
        $this->frm->addHidden('id', $this->record['id']);
        $this->frm->addCheckbox('delete_image');
        $this->frm->addDate('publish_on_date', $this->record['publish_on']);
        $this->frm->addTime('publish_on_time', date('H:i', $this->record['publish_on']));

        // set hidden values
        $rbtHiddenValues[] = array('label' => Language::lbl('Hidden', $this->URL->getModule()), 'value' => 'Y');
        $rbtHiddenValues[] = array('label' => Language::lbl('Published'), 'value' => 'N');

        $this->frm->addRadiobutton('hidden', $rbtHiddenValues, $this->record['hidden']);

        // if we have multiple templates, add a dropdown to select them

        if (count($this->templates) > 1) {
            $this->frm->addDropdown('template', array_combine($this->templates, $this->templates), $this->record['template']);
        }


        foreach ($this->languages as &$language) {
            $field = $this->frm->addText('name_' . $language['abbreviation'], isset($this->record['content'][$language['abbreviation']]['name']) ? $this->record['content'][$language['abbreviation']]['name'] : '', null, 'form-control title', 'form-control danger title');
            $language['name_field'] = $field->parse();

            $field = $this->frm->addEditor('description_' . $language['abbreviation'], isset($this->record['content'][$language['abbreviation']]['description']) ? $this->record['content'][$language['abbreviation']]['description'] : '');
            $language['description_field'] = $field->parse();

            $field = $this->frm->addDropdown('pages_' . $language['abbreviation'], BackendBlocksModel::getPagesForDropdown($language['abbreviation']), $this->record['content'][$language['abbreviation']]['link'])->setDefaultElement('', '');
            $language['pages_field'] = $field->parse();

            $field = $this->frm->addText('link_' . $language['abbreviation'], isset($this->record['content'][$language['abbreviation']]['link']) ? $this->record['content'][$language['abbreviation']]['link'] : '');
            $language['link_field'] = $field->parse();
        }
    }

    /**
     * Parse the page
     */
    protected function parse()
    {
        parent::parse();

        $this->tpl->assign('languages', $this->languages);
        $this->tpl->assign('draft', $this->record['status'] == 'draft');
        $this->tpl->assign('record', $this->record);
        $this->tpl->assign('imageUrl', SiteHelpersHelper::getImageUrl($this->record['image'], $this->getModule()));
        $this->tpl->assign('templates', count($this->templates) > 1);
    }

    /**
     * Validate the form
     */
    protected function validateForm()
    {
        if ($this->frm->isSubmitted()) {

            // get the status
            $status = \SpoonFilter::getPostValue('status', array('active', 'draft'), 'active');

            $this->frm->cleanupFields();

            // validation
            $fields = $this->frm->getFields();

            SiteHelpersHelper::validateImage($this->frm, 'image');
            $this->frm->getField('publish_on_date')->isValid(Language::err('DateIsInvalid'));
            $this->frm->getField('publish_on_time')->isValid(Language::err('TimeIsInvalid'));

            foreach ($this->languages as $key => $language) {
                $field = $this->frm->getField('name_' . $this->languages[$key]['abbreviation'])->isFilled(Language::getError('FieldIsRequired'));
                $this->languages [$key]['name_errors'] = $this->frm->getField('name_' . $this->languages[$key]['abbreviation'])->getErrors();
            }


            if ($this->frm->isCorrect()) {
                $item['id'] = $this->id;
                $item['hidden'] = $fields['hidden']->getValue();
                $item['publish_on'] = Model::getUTCDate(null, Model::getUTCTimestamp($this->frm->getField('publish_on_date'), $this->frm->getField('publish_on_time')));
                $item['status'] = $status;
                $item['template'] = count($this->templates) > 1 ? $fields['template']->getValue() : $this->templates[0];


                $imagePath = SiteHelpersHelper::generateFolders($this->getModule(), 'image', array('1200x630', '600x315'));

                if ($fields['delete_image']->isChecked()) {
                    $item['image'] = null;
                    Model::deleteThumbnails(FRONTEND_FILES_PATH . '/' . $this->getModule() . '/image', $this->record['image']);
                }

                // image provided?
                if ($fields['image']->isFilled()) {
                    // replace old image
                    if ($this->record['image']) {
                        $item['image'] = null;
                        Model::deleteThumbnails(FRONTEND_FILES_PATH . '/' . $this->getModule() . '/image', $this->record['image']);
                    }

                    // build the image name
                    $item['image'] = uniqid() . '.' . $fields['image']->getExtension();

                    // upload the image & generate thumbnails
                    $fields['image']->generateThumbnails($imagePath, $item['image']);
                }

                $content = array();

                foreach ($this->languages as $language) {
                    $specific['extra_id'] = $this->record['content'][$language['abbreviation']]['extra_id'];
                    $specific['block_id'] = $item['id'];
                    $specific['language'] = $language['abbreviation'];
                    $specific['name'] = $this->frm->getField('name_' . $language['abbreviation'])->getValue();
                    $specific['link'] = $this->frm->getField('link_' . $language['abbreviation'])->getValue();
                    $specific['description'] = $this->frm->getField('description_' . $language['abbreviation'])->getValue() ? $this->frm->getField('description_' . $language['abbreviation'])->getValue() : null;
                    $content[$language['abbreviation']] = $specific;
                }

                BackendBlocksModel::update($item);
                BackendBlocksModel::updateContent($content, $item['id']);

                Model::triggerEvent(
                    $this->getModule(), 'after_edit', $item
                );
                $this->redirect(
                    Model::createURLForAction('Edit') . '&report=edited&id=' . $item['id']
                );
            }
        }
    }
}
