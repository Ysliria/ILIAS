<?php
declare(strict_types=1);
namespace ILIAS\UI\examples\Counter\Novelty;

function base()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    return $renderer->render($f->symbol()->glyph()->mail("#")
        ->withCounter($f->counter()->novelty(3)));
}
