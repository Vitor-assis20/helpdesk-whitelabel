<?php

namespace App\Enums;

enum MessageKind: string
{
    /** Visível para o cliente. */
    case Reply = 'reply';

    /** Nota interna: só a equipe vê. */
    case Note = 'note';
}
