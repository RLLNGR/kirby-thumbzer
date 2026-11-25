<?php

declare(strict_types=1);

namespace Rllngr\Thumbzer;

use Kirby\Form\Field\DisplayField;

/**
 * Kirby 6: the "compare ICC profiles" link, as a field that stores nothing.
 *
 * Kirby 6 removed custom sections. The plugin maps the `thumbzer-icc-button`
 * section type onto this field, so file blueprints written for Kirby 4 and 5
 * keep working unchanged.
 */
class IccButtonField extends DisplayField
{
    public function props(): array
    {
        return [
            ...parent::props(),
            'link' => $this->model()->kirby()->url() . '/test-icc?file=' . $this->model()->id(),
        ];
    }
}
