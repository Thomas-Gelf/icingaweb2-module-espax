ESPA-X Notifications for Icinga
===============================

[![Coding Standards](https://github.com/Thomas-Gelf/icingaweb2-module-espax/actions/workflows/CodingStandards.yml/badge.svg)](https://github.com/Thomas-Gelf/icingaweb2-module-espax/actions/workflows/CodingStandards.yml)

The European Selective Paging Manufacturer’s Association (ESPA) defined
**ESPA 4.4.4** as a "Proposal for Serial Data Interface For Paging Equipment".
It's a message-based intercommunication protocol for building control systems
(e.g. SCADA/SPS), nurse call solutions, pagers, (phone) switchboards and the
like.  Specified in 1984, the protocol has since then become an industry
standard.

As it has was designed for serial links (RS-485, RS-232 or RS-422), which
does no longer fit all needs in today's IP-based infrastructures. That's
why [ESPA-X](https://www.espa-x.org/) has been founded, which defines itself
as:

> ...an "open community of interests between a wide range of different
> companies that all support the ESPA-X protocol in some shape or form
> (e.g. as a manufacturer of alarm servers, BCM systems, nurse call systems,
> or as a consulting firm)

This community specified ESPA-X, is a proprietary successor for ESPA 4.4.4.
ESPA-X stands for: **E**nhanced **S**ignaling **P**rotocol for **A**larm
**P**rocesses - **X**ML-based, and is therefore based on XML (and TCP).

Architecture / Overview
-----------------------

The main purpose of this module is shipping [Icinga](https://www.icinga.com)
**Notifications** to alerting systems with ESPA-X support, and **acknowledging**
Icinga problems, as soon as someone received/confirmed an Alert.

* **Background Daemon**: keeps connections to your configured ESPA-X peers (which
  should then receive your notifications) alive
* **Notification Command**: to be used by Icinga or compatible systems, shipping
  Icinga notifications to the background daemon via it's Unix socket
* **Acknowledgement back-channel**: allows to acknowledge problems in your monitoring
  systems, as soon as an alarm has been shipped/received. Currently, this has been
  implemented for the Icinga 2 API transport
* **Clearing sent Alerts on recovery**: once a problem recovers, this module should
  be able to trigger cancellations for notifications, which have already been sent
  *(TBD)*


Requirements
------------

* Icinga Web 2 (&gt;= 2.10)
* PHP (&gt;= 7.2 or 8.x - 64bit only)
* php-xml, php-simplexml
* php-pcntl (might already be built into your PHP binary)
* php-posix (on RHEL/CentOS this is php-process, or rh-php7x-php-process)
* The following Icinga modules must be installed and enabled:
  * [incubator](https://github.com/Icinga/icingaweb2-module-incubator) (>=0.21)

Once you got Icinga Web 2 up and running, all required dependencies should
already be there. In case something is missing, the daemon will tell you so.

Installation
------------

### Module installation (or upgrade)

This script downloads the [latest version](https://github.com/Thomas-Gelf/icingaweb2-module-espax/releases)
and extract installs it to the default Icinga Web 2 module directory. An eventually
existing module installation will be replaced, so this can be used for upgrades too:

```shell
# You can customize these settings, but we suggest to stick with our defaults:
MODULE_VERSION="0.0.0"
DAEMON_USER="icingaespax"
DAEMON_GROUP="icingaweb2"
ICINGAWEB_MODULEPATH="/usr/share/icingaweb2/modules"
REPO_URL="https://github.com/Thomas-Gelf/icingaweb2-module-espax"
TARGET_DIR="${ICINGAWEB_MODULEPATH}/espax"
URL="${REPO_URL}/archive/refs/tags/v${MODULE_VERSION}.tar.gz"

# systemd defaults:
SOCKET_PATH=/run/icinga-espax
TMPFILES_CONFIG=/etc/tmpfiles.d/icinga-espax.conf

getent passwd "${DAEMON_USER}" > /dev/null || useradd -r -g "${DAEMON_GROUP}" \
  -d /var/lib/${DAEMON_USER} -s /bin/false ${DAEMON_USER}
install -d -o "${DAEMON_USER}" -g "${DAEMON_GROUP}" -m 0750 /var/lib/${DAEMON_USER}
install -d -m 0755 "${TARGET_DIR}"

test -d "${TARGET_DIR}_TMP" && rm -rf "${TARGET_DIR}_TMP"
test -d "${TARGET_DIR}_BACKUP" && rm -rf "${TARGET_DIR}_BACKUP"
install -d -o root -g root -m 0755 "${TARGET_DIR}_TMP"
wget -q -O - "$URL" | tar xfz - -C "${TARGET_DIR}_TMP" --strip-components 1 \
  && mv "${TARGET_DIR}" "${TARGET_DIR}_BACKUP" \
  && mv "${TARGET_DIR}_TMP" "${TARGET_DIR}" \
  && rm -rf "${TARGET_DIR}_BACKUP"

echo "d ${SOCKET_PATH} 0755 ${DAEMON_USER} ${DAEMON_GROUP} -" > "${TMPFILES_CONFIG}"
cp -f "${TARGET_DIR}/contrib/systemd/icinga-espax.service" /etc/systemd/system/
systemd-tmpfiles --create "${TMPFILES_CONFIG}"

icingacli module enable espax
systemctl daemon-reload
systemctl enable icinga-espax.service
systemctl restart icinga-espax.service
```
Configuration
-------------

You can find the configuration for this module in `ICINGAWEB_CONFIGDIR/modules/espax`,
which usually resolves to the directory `/etc/icingaweb2/modules/espax`. There are two,
files, `config.ini` and `connections.ini`.

### config.ini

```ini
[db]
; reference to the Icinga Web resource in /etc/icingaweb2/resources.ini
resource = "ESPA-X Database"

[node]
uuid = 9c9adf1e-22dc-4372-b445-0ceaf3a1f9e2
```

### connections.ini

The following settings are allowed, **bold** ones are required. Usually, you
shouldn't be required to tweak optional settings:

| Setting            | Description                                                                                                                                                                       |
|--------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **address**        | Our peer address, can be both an IP address or a domain name. This setting is required                                                                                            |
| **port**           | Target TCP port. Numeric, required                                                                                                                                                |
| **username**       | Username, to authenticate our sessions. Required.                                                                                                                                 |
| **password**       | The related password, also required                                                                                                                                               |
| heartbeat_interval | We're sending keep-alive packets every `heartbeat_interval`.<br />Setting this to 0 disables the heartbeat, which is strongly discouraged.<br />Default: `10` (seconds)           |
| heartbeat_timeout  | Our peer must respond to our heartbeat packet within `heartbeat_timeout` seconds.<br />Default: `2` (seconds)                                                                     |
| reconnect_interval | In case the connection has been lost (or been teared down, because of failing heartbeats), we'll try to reconnect after `reconnect_timeout` seconds.<br />Default: `10` (seconds) |

### Example Configuration (connections.ini)

```ini
[MobiCall PROD]
address = "mobicall.prod.example.com"
port = 21501
username = "icinga"
password = "***"


[MobiCall TEST]
address = "192.0.10.42"
port = 21501
username = "icinga"
password = "***"
; More aggressive timings for our test environment:
heartbeat_interval = 5
heartbeat_timeout = 1
reconnect_interval = 5
```

FAQ
---

### Running without SystemD

systemd is not a hard requirement, you can use any supervisor you want. The
command you're looking for is:

    /usr/bin/icingacli expax daemon run
