<?php

declare(strict_types=1);

namespace Sitegeist\Nodemerobis\Domain\Specification;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
class TetheredNodePresetNameSpecification
{
    public function __construct(
        public readonly string $presetName
    ) {
    }

    public function __toString(): string
    {
        return 'preset:' . $this->presetName;
    }
}
