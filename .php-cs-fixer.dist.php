<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()->in('src');

$rules = [
    '@PSR12'         => true,
    '@PhpCsFixer'    => true,
    '@Symfony'       => true,
    '@Symfony:risky' => true
];

$config = new PhpCsFixer\Config();

return $config->setRiskyAllowed(true)->setRules($rules)->setFinder($finder);
