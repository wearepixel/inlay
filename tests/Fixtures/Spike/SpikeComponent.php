<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Tests\Fixtures\Spike;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\Inlay\Support\LinkValue;

final class SpikeComponent extends Component
{
    public function __construct(
        private SpikeRepository $repository,
        public string $heading = 'Welcome',
        public SpikeImageSize $imageSize = SpikeImageSize::Large,
        public ?LinkValue $action = null,
    ) {}

    public function repositoryLabel(): string
    {
        return $this->repository->label();
    }

    public function render(): View
    {
        return view('spike-component');
    }
}
