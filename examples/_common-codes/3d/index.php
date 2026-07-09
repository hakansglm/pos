<?php

/** @var \Mews\Pos\PosInterface $pos */
/** @var string $baseUrl */
/** @var array<string, array<string, string>> $testCards */

require '../../_templates/_header.php';

$url = $baseUrl.'form.php';
$card = createCard($pos, $testCards['visa1']);

require '../../_templates/_credit_card_form.php';
require '../../_templates/_footer.php';
