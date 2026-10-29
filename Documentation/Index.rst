===================
pxe_composer_report
===================

:Extension key: pxe_composer_report
:Package name: pixelegg/pxe-composer-report
:Version: 1.2.0
:Language: en
:Author: pixelegg
:License: GPL-2.0-or-later

Read-only composer update report for TYPO3 13.4 LTS and 14: a backend module
that lists outdated packages and known security advisories of the project, a
scheduler task and a CLI command that store the report and optionally email it.

.. figure:: /Images/backend-module.png
   :alt: Backend module with summary chips, two security advisories, outdated TYPO3 packages and other packages

   The backend module on a demo installation with TYPO3 14.3.8, an older Guzzle
   and an old PHPMailer that has known advisories.

What it does
============

* Lists outdated direct dependencies, TYPO3 packages first, sorted by impact
  (major, minor, patch). Abandoned packages are flagged.
* "Latest" is the newest stable version within the installed major; a newer
  major is shown as a separate hint only. Packages that are current within
  their major but have a newer major are listed in their own section, not
  counted as outdated.
* Lists security advisories (package, severity, CVE, link) above everything
  else, most severe first.
* Keeps a capped history of runs and shows the trend of the outdated count.
* Optional HTML and plain-text email to a list of recipients, optionally only
  on findings, plus a test-mail button.
* Optional short plain-language summary via the Anthropic API (off by default).
* Clear messages instead of a blank page when composer cannot be run.

The extension never changes the project. It only runs ``composer outdated``
(plain and ``--minor-only``) and ``composer audit`` with fixed argument lists,
or reads ``composer.lock`` and ``composer.json``.

Requirements
============

* TYPO3 13.4 LTS or 14 with the scheduler extension, PHP 8.2 or newer
* Composer-based installation (``composer.json`` and ``composer.lock`` in the
  project root)
* For the ``composer`` collector: the ``composer`` binary reachable by the PHP
  process and ``proc_open()`` enabled; otherwise the Packagist collector is used.

Installation
============

.. code-block:: bash

   composer require pixelegg/pxe-composer-report
   vendor/bin/typo3 extension:setup

Configuration
=============

*Admin Tools > Settings > Extension Configuration > pxe_composer_report* in
TYPO3 13.4, *System > Settings > Extension Configuration > pxe_composer_report*
in TYPO3 14. The settings are grouped in the tabs *Basic* (collector, report and
history), *Privacy*, *Email* and *AI summary*:

.. list-table::
   :header-rows: 1

   * - Setting
     - Default
     - Meaning
   * - ``collectorMode``
     - ``auto``
     - ``composer`` runs the composer CLI, ``packagist`` asks the Packagist API,
       ``auto`` uses composer when ``proc_open()`` and the binary are available
       and falls back to Packagist (with a notice in the module).
   * - ``composerBinary``
     - ``composer``
     - ``composer`` (searched in the PATH of the PHP process) or a full path such
       as ``/usr/local/bin/composer`` or ``/opt/composer.phar``.
   * - ``projectLabel``
     - site name
     - Project name shown in the module and in the email.
   * - ``keepRuns``
     - ``50``
     - Number of stored runs; older runs are deleted after each run. ``0`` keeps
       everything.
   * - ``resolveLatestViaPackagist``
     - on
     - Off: the extension itself never contacts packagist.org. The composer
       collector works without any lookup of its own; the Packagist collector
       stops with a clear error.
   * - ``privateVendors``
     - empty
     - Comma-separated vendor prefixes or full package names, e.g.
       ``acme, mycorp/intranet``. Never sent to Packagist, the advisories API or
       the AI summary; listed as "private, not checked" with the Packagist
       collector.
   * - ``emailEnabled``
     - off
     - Send the report by email after each scheduled or CLI run.
   * - ``emailRecipients``
     - empty
     - Comma-separated recipient addresses.
   * - ``emailOnlyOnFindings``
     - on
     - Only send when something is outdated or an advisory exists.
   * - ``emailLanguage``
     - ``en``
     - ``en`` or ``de``; language of the email and of the AI summary.
   * - ``aiEnabled``
     - off
     - Generate a short summary with the Anthropic API.
   * - ``anthropicApiKey``
     - empty
     - API key, required for the summary.
   * - ``anthropicModel``
     - ``claude-haiku-5-5``
     - Model id for the summary; ``claude-sonnet-5-5`` or ``claude-opus-5-5`` for a more capable (and more expensive) one.

The sender address is TYPO3's system mail address. When ``HOME`` is unset
(php-fpm), ``COMPOSER_HOME`` is set to ``var/pxe_composer_report/composer-home``.

.. figure:: /Images/backend-extension-configuration.png
   :alt: Extension configuration of pxe_composer_report, tab Privacy, with the settings for Packagist lookups and private vendors

   Extension configuration, tab "Privacy".

Usage
=====

Backend module
--------------

*Admin Tools > Composer Report* in TYPO3 13.4, *Administration > Composer
Report* in TYPO3 14 (the main module "Admin Tools" was renamed). Administrators
only. The buttons sit in the module's docheader: *Refresh now* runs a report
immediately; *Send test mail* sends the current report to one address or to the
configured recipients. Both are POST requests with a CSRF token.

.. figure:: /Images/backend-module-de.png
   :alt: The same backend module with German labels

   The module with the backend language set to German.

Scheduler
---------

Add the task *Composer update report (read-only)* from the group *Composer
Report* (*System > Scheduler* in TYPO3 13.4, *Administration > Scheduler* in
TYPO3 14), e.g. weekly. A failed run is stored with status "error" and marks the
task execution as failed.

CLI
---

.. code-block:: bash

   vendor/bin/typo3 pxe:composer-report:run           # run and print a summary
   vendor/bin/typo3 pxe:composer-report:run --mail    # also email the configured recipients
   vendor/bin/typo3 pxe:composer-report:run --json    # print the stored report as JSON

Exit codes: ``0`` no security advisories, ``1`` security advisories found,
``2`` the run failed.

Email
-----

With ``emailEnabled`` on, scheduled and CLI runs (``--mail``) send the report
as HTML with a plain-text alternative.

.. figure:: /Images/email.png
   :alt: Report email with summary chips, security advisories, TYPO3 packages and other packages

   HTML part of the report email (English).

Privacy and external connections
================================

Nothing else than the following is sent; the extension does not read
``auth.json`` or any credentials.

.. list-table::
   :header-rows: 1

   * - When
     - Target
     - Data sent
   * - ``composer`` collector
     - every repository configured in the project's ``composer.json``
     - whatever composer itself sends for ``composer outdated`` and
       ``composer audit``, with the project's own configuration and credentials.
       ``composer audit`` sends the names of all installed packages to the
       Packagist advisories API (composer behaviour). The extension itself makes
       no request in this mode.
   * - ``packagist`` collector
     - ``https://repo.packagist.org/p2/<vendor>/<package>.json``
     - one GET per direct dependency not in ``privateVendors``; the package name
       is part of the URL.
   * - ``packagist`` collector
     - ``https://packagist.org/api/security-advisories/``
     - names of all direct dependencies not in ``privateVendors``.
   * - defensive fallback for rows without a within-major version (does not
       happen with the shipped collectors), ``resolveLatestViaPackagist`` on
     - ``https://repo.packagist.org/p2/<vendor>/<package>.json``
     - the package name, unless in ``privateVendors``.
   * - ``aiEnabled`` on
     - ``https://api.anthropic.com/v1/messages``
     - package counts; per advisory the package name, severity, CVE and title;
       per outdated TYPO3 package the name, installed version, latest version,
       next major and impact. ``privateVendors`` are removed first. No host
       names, paths, descriptions, links or user data.
   * - ``emailEnabled`` on / test mail
     - the TYPO3 mail transport
     - the complete report, to the configured recipients.

``resolveLatestViaPackagist = 0`` guarantees that the extension itself never
opens a connection to packagist.org; ``privateVendors`` guarantees that the
listed packages never appear in any request made by the extension itself.

Optional integration
====================

When the separate extension ``pxe_hub`` is installed, the report also appears
as a card in its overview module. Without it nothing changes.

Changelog
=========

See ``CHANGELOG.md`` in the extension root.
