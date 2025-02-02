<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\ResourceStorage\Consumer;

use ILIAS\ResourceStorage\Consumer\StreamAccess\StreamAccess;
use ILIAS\ResourceStorage\Flavour\Flavour;
use ILIAS\ResourceStorage\Policy\FileNamePolicy;
use ILIAS\ResourceStorage\Policy\NoneFileNamePolicy;
use ILIAS\ResourceStorage\Resource\StorableResource;
use ILIAS\ResourceStorage\Resource\StorableContainerResource;

/**
 * Class ConsumerFactory
 * @author Fabian Schmid <fabian@sr.solutions.ch>
 */
class ConsumerFactory
{
    protected \ILIAS\ResourceStorage\Policy\FileNamePolicy $file_name_policy;
    /**
     * @readonly
     */
    private \ILIAS\HTTP\Services $http;
    private StreamAccess $stream_access;

    /**
     * ConsumerFactory constructor.
     * @param FileNamePolicy|null $file_name_policy
     */
    public function __construct(
        StreamAccess $stream_access,
        FileNamePolicy $file_name_policy = null
    ) {
        $this->stream_access = $stream_access;
        global $DIC;
        $this->file_name_policy = $file_name_policy ?? new NoneFileNamePolicy();
        $this->http = $DIC->http();
    }

    public function download(StorableResource $resource): DownloadConsumer
    {
        return new DownloadConsumer(
            $this->http,
            $resource,
            $this->stream_access,
            $this->file_name_policy
        );
    }

    public function inline(StorableResource $resource): InlineConsumer
    {
        return new InlineConsumer(
            $this->http,
            $resource,
            $this->stream_access,
            $this->file_name_policy
        );
    }

    public function fileStream(StorableResource $resource): FileStreamConsumer
    {
        return new FileStreamConsumer(
            $resource,
            $this->stream_access
        );
    }

    /**
     * @deprecated
     */
    public function absolutePath(StorableResource $resource): AbsolutePathConsumer
    {
        return new AbsolutePathConsumer(
            $resource,
            $this->stream_access,
            $this->file_name_policy
        );
    }

    public function src(StorableResource $resource, SrcBuilder $src_builder): SrcConsumer
    {
        return new SrcConsumer(
            $src_builder,
            $resource,
            $this->stream_access
        );
    }

    public function flavourUrl(Flavour $flavour, SrcBuilder $src_builder): FlavourURLs
    {
        return new FlavourURLs(
            $src_builder,
            $flavour
        );
    }

    public function downloadMultiple(
        array $resources,
        ?string $zip_filename = null
    ): DownloadMultipleConsumer {
        return new DownloadMultipleConsumer(
            $resources,
            $this->stream_access,
            $this->file_name_policy,
            $zip_filename ?? 'Download.zip'
        );
    }

    public function containerZIP(
        StorableContainerResource $resource,
    ): ContainerConsumer {
        return new ContainerZIPAccessConsumer(
            $resource,
            $this->stream_access
        );
    }

    public function containerURI(
        StorableContainerResource $resource,
        SrcBuilder $src_builder,
        string $start_file = 'index.html',
        float $valid_for_at_least_minutes = 60.0
    ): ContainerConsumer {
        return new ContainerURIConsumer(
            $src_builder,
            $resource,
            $this->stream_access,
            $start_file,
            $valid_for_at_least_minutes
        );
    }
}
