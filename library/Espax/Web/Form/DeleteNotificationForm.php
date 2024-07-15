<?php

namespace Icinga\Module\Espax\Web\Form;

class DeleteNotificationForm extends InlineActionForm
{
    protected function assemble()
    {
        $this->provideAction($this->translate('Cancel notification'), $this->translate('Cancel this notification'));
    }
}
