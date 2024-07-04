<?php

namespace Icinga\Module\Espax\Clicommands;

class SendCommand extends RpcCommand
{
    /**
     * Send a Notification
     *
     * USAGE
     *
     * icingacli espax send notification --host <name> [--service <service>] [options]
     *
     * OPTIONS
     *   --host <hostname>            Icinga Hostname, required
     *   --service <servicename>      Icinga Service Name, optional
     *   --destination <destination>  Alarm, Process -> CP-CALLINGNAME. Required
     *   --message <message>          Alarm message text, required
     *   --connection <connection>    ESPA-X Connection name, defaults to first configured one
     *   --timeout <seconds>          Alarm will be dismissed after this amount of seconds, an Icinga-Renotification
     *                                will then generate a new alarm for the very same problem
     */
    public function notificationAction(): void
    {
        self::rpc('espax.sendIcingaProblem', [
            'host'        => $this->params->getRequired('host'),
            'service'     => $this->params->get('service'),
            'destination' => $this->params->getRequired('destination'),
            'message'     => $this->params->getRequired('message'),
            'connection'  => $this->params->get('connection'),
            'timeout'     => $this->params->get('timeout'),
        ]);
    }

    /**
     * Send a Recovery
     *
     *
     *
     * @api
     * @return void
     * @throws \Icinga\Exception\MissingParameterException
     */
    public function recoveryAction()
    {
        self::rpc('espax.recoverIcingaProblem', [
            'host'        => $this->params->getRequired('host'),
            'service'     => $this->params->get('service'),
        ]);
    }
}
