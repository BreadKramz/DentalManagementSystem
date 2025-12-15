<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class LoginListener
{
    #[AsEventListener(event: 'user')]
    public function onUser($event): void
    {
        // ...
    }
}
