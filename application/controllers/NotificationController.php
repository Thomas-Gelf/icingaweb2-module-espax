<?php

namespace Icinga\Module\Espax\Controllers;

use gipfl\Web\Widget\Hint;
use Icinga\Module\Espax\Web\Form\DeleteNotificationForm;
use Icinga\Module\Espax\Web\Table\NotificationDetails;
use Icinga\Module\Espax\Web\Table\PacketTraceTable;
use Icinga\Web\Notification;
use ipl\Html\Html;

use function Clue\React\Block\await;

/**
 * @api
 */
class NotificationController extends Controller
{
    use LazyDb;

    /**
     * @api
     */
    public function indexAction()
    {
        $reference = $this->params->getRequired('problem_reference');
        $this->addTitle($this->translate('Notification Details'));
        $this->addSingleTab($this->translate('Notification'));
        $db = $this->db();
        $notification = $db->fetchRow(
            $db->select()->from('espax_notification')->where('problem_reference = ?', $reference)
        );
        $isHistoric = false;
        if (! $notification) {
            $notification = $db->fetchRow(
                $db->select()->from('espax_notification_history')->where('problem_reference = ?', $reference)
            );
            $isHistoric = true;
        }
        if ($notification) {
            $this->content()->add(new NotificationDetails($notification));
        } else {
            $this->content()->add(Hint::error(sprintf('There is no notification with this reference: %s', $reference)));
        }
        if (! $isHistoric && $this->hasPermission('expax/deleteNotification')) {
            $this->addDeleteForm($notification->ts);
        }
        if (! $this->hasPermission('espax/showTrace')) {
            return;
        }

        $table = new PacketTraceTable($db);
        $this->setAutorefreshInterval(10);
        $table->getQuery()->where('problem_reference = ?', $reference);
        if ($table->count() === 0) {
            $this->content()->add(Hint::info($this->translate('No related packet trace has been found')));
        } else {
            $this->content()->add(Html::tag('h3', $this->translate('Related packet trace')));
            $table->renderTo($this);
        }
    }

    protected function addDeleteForm(int $ts): void
    {
        $deleteForm = new DeleteNotificationForm();
        $deleteForm->on('success', function () use ($ts) {
            try {
                await($this->remoteClient()->request('espaxDb.deleteNotification', [
                    'ts' => $ts,
                    'username' => $this->Auth()->getUser()->getUsername(),
                ]));
            } catch (\Exception $e) {
                $this->content()->prepend(Hint::error($e->getMessage()));
                return;
            }

            Notification::success($this->translate('Notification has been deleted'));
            $this->getResponse()->redirectAndExit('espax/notifications');
        });
        $deleteForm->handleRequest($this->getServerRequest());
        $this->controls()->add($deleteForm);
    }
}
