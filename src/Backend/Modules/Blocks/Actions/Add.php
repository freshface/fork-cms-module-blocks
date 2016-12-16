<?php

namespace Backend\Modules\Blocks\Actions;

use Backend\Core\Engine\Base\ActionAdd;
use Backend\Core\Engine\Form;
use Backend\Core\Language\Language;
use Backend\Core\Engine\Model;
use Backend\Modules\Blocks\Engine\Model as BackendBlocksModel;

use Backend\Modules\SiteHelpers\Engine\Helper as SiteHelpersHelper;
use Backend\Modules\SiteHelpers\Engine\Model as SiteHelpersModel;
use Backend\Modules\SiteHelpers\Engine\Assets as SiteHelpersAssets;
use Common\Uri as CommonUri;

/**
 * This is the add-action, it will display a form to create a new item
 *
 * @author Frederik Heyninck <frederik@figure8.be>
 */
class Add extends ActionAdd
{
    /**
     * Execute the actions
     */
    public function execute()
    {
        parent::execute();
        $this->templates = BackendBlocksModel::getTemplates();

        $this->languages = SiteHelpersHelper::getActiveLanguages();

        $this->loadForm();
        $this->validateForm();

        $this->parse();
        $this->display();
    }

    /**
     * Load the form
     */
    protected function loadForm()
    {
        $this->frm = new Form('add');

        $this->frm->addImage('image');

        // set hidden values
        $rbtHiddenValues[] = array('label' => Language::lbl('Hidden', $this->URL->getModule()), 'value' => 'Y');
        $rbtHiddenValues[] = array('label' => Language::lbl('Published'), 'value' => 'N');

        $this->frm->addRadiobutton('hidden', $rbtHiddenValues, 'N');

        $this->frm->addDate('publish_on_date');
        $this->frm->addTime('publish_on_time');

        if (count($this->templates) > 1) {
            $this->frm->addDropdown('template', array_combine($this->templates, $this->templates));
        }


        foreach ($this->languages as &$language) {
            $field = $this->frm->addText('name_' . $language['abbreviation'], isset($this->record['content'][$language['abbreviation']]['name']) ? $this->record['content'][$language['abbreviation']]['name'] : '', null, 'form-control title', 'form-control danger title');
            $language['name_field'] = $field->parse();

            $field = $this->frm->addEditor('description_' . $language['abbreviation'], isset($this->record['content'][$language['abbreviation']]['description']) ? $this->record['content'][$language['abbreviation']]['description'] : '');
            $language['description_field'] = $field->parse();

            $field = $this->frm->addDropdown('pages_' . $language['abbreviation'], BackendBlocksModel::getPagesForDropdown($language['abbreviation']))->setDefaultElement('', '');
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
                // build the item
                $item['hidden'] = $fields['hidden']->getValue();
                $item['sequence'] = BackendBlocksModel::getMaximumSequence() + 1;
                $item['publish_on'] = Model::getUTCDate(null, Model::getUTCTimestamp($this->frm->getField('publish_on_date'), $this->frm->getField('publish_on_time')));
                $item['status'] = $status;
                $item['template'] = count($this->templates) > 1 ? $fields['template']->getValue() : $this->templates[0];

                $imagePath = SiteHelpersHelper::generateFolders($this->getModule(), 'image', array('1200x630', '600x315'));

                // image provided?
                if ($fields['image']->isFilled()) {
                    // build the image name
                    $item['image'] = uniqid() . '.' . $fields['image']->getExtension();

                    // upload the image & generate thumbnails
                    $fields['image']->generateThumbnails($imagePath, $item['image'], 0777);
                }

                $item['id'] = BackendBlocksModel::insert($item);

                $content = array();


                foreach ($this->languages as $language) {
                    $specific['block_id'] = $item['id'];

                    $specific['language'] = $language['abbreviation'];
                    $specific['name'] = $this->frm->getField('name_' . $language['abbreviation'])->getValue();
                    $specific['link'] = $this->frm->getField('link_' . $language['abbreviation'])->getValue();
                    $specific['description'] = $this->frm->getField('description_' . $language['abbreviation'])->getValue() ? $this->frm->getField('description_' . $language['abbreviation'])->getValue() : null;
                    $content[$language['abbreviation']] = $specific;
                }

                // insert it
               BackendBlocksModel::insertContent($content);

                Model::triggerEvent(
                    $this->getModule(), 'after_add', $item
                );
                $this->redirect(
                    Model::createURLForAction('Edit') . '&report=added&id=' . $item['id']
                );
            }
        }
    }
}
