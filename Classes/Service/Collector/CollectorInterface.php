<?php

declare(strict_types=1);

namespace Pixelegg\PxeComposerReport\Service\Collector;

interface CollectorInterface
{
    /**
     * Raw data of one run.
     *
     * - outdated: rows with name, version (installed), latestInMajor,
     *   latestOverall, description and abandoned
     * - advisories: package name => list of advisories (title, cve, severity, link)
     * - private: names of direct dependencies that were deliberately not checked
     *   (see PackagePrivacy); empty for the composer collector, which lets
     *   composer check them with the project's own repositories
     *
     * @return array{
     *     outdated: list<array<string,mixed>>,
     *     advisories: array<string,list<array<string,mixed>>>,
     *     collector: string,
     *     private: list<string>
     * }
     *
     * @throws CollectorException
     */
    public function collect(): array;
}
