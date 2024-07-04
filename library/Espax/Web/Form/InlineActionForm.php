<?php

namespace Icinga\Module\Espax\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\ZfDb\Adapter\Adapter as DbAdapter;
use ipl\Html\FormElement\SubmitElement;

abstract class InlineActionForm extends Form
{
    use TranslationHelper;

    protected $method = 'POST';

    /** @var boolean|null */
    protected $hasBeenSubmitted;

    protected $defaultDecoratorClass = null;

    protected $defaultAttributes = ['class' => 'inline'];

    protected function provideAction($label, $title = null)
    {
        $next = new SubmitElement('next', [
            'class' => 'link-button icon-cancel',
            'label' => sprintf('[ %s ]', $label),
            'title' => $title,
        ]);
        $submit = new SubmitElement('submit', [
            'label' => sprintf(
                $this->translate('Really %s'),
                $label
            )
        ]);
        $cancel = new SubmitElement('cancel', [
            'label' => $this->translate('Cancel')
        ]);
        $this->toggleNextSubmitCancel($next, $submit, $cancel);
    }

    protected function toggleNextSubmitCancel(
        SubmitElement $next,
        SubmitElement $submit,
        SubmitElement $cancel
    ) {
        if ($this->hasBeenSent()) {
            $this->addElement($submit);
            $this->addElement($cancel);
            if ($cancel->hasBeenPressed()) {
                // HINT: we might also want to redirect on cancel and stop here,
                //       but currently we have no Response
                $this->setSubmitted(false);
                $this->remove($submit);
                $this->remove($cancel);
                $this->add($next);
                $this->setSubmitButton($next);
            } else {
                $this->setSubmitButton($submit);
                $this->remove($next);
            }
        } else {
            $this->addElement($next);
        }
    }
}
