<?php

namespace App\Enums;

/**
 * Por onde o chamado chegou.
 */
enum TicketChannel: string
{
    case Portal = 'portal';
    case Widget = 'widget';
    case Email = 'email';
    case Panel = 'panel';
}
