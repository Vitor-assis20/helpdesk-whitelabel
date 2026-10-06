<?php
use Illuminate\Support\Facades\Broadcast;
echo 'host: ', config('broadcasting.connections.reverb.options.host'), ':', config('broadcasting.connections.reverb.options.port'), PHP_EOL;
Broadcast::on('teste')->as('ping')->with(['msg' => 'olá do Laravel'])->sendNow();
echo "enviado\n";
