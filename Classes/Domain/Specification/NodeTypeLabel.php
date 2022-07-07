<?php

declare(strict_types=1);

namespace Sitegeist\Nodemerobis\Domain\Specification;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
class NodeTypeLabel
{
    public function __construct(
        public readonly string $label
    ){
    }
}
