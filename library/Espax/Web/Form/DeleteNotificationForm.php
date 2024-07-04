<?php

namespace Icinga\Module\Espax\Web\Form;

class DeleteNotificationForm extends InlineActionForm
{
    protected function assemble()
    {
        $this->provideAction($this->translate('Delete'), $this->translate('Delete this notification'));
    }
}
