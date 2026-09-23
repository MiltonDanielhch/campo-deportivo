<?php

namespace App\Console\Commands;

use App\Models\Funcionario;
use Illuminate\Console\Command;

class VincularFuncionarioMamore extends Command
{
    protected $signature = 'canchas:vincular-funcionario {usuario} {mamore_id}';

    protected $description = 'Vincula un funcionario local de canchas con su mamore_id de Ibare (autorización local)';

    public function handle(): int
    {
        $funcionario = Funcionario::where('usuario', $this->argument('usuario'))->first();

        if (! $funcionario) {
            $this->error('Funcionario local no encontrado');

            return self::FAILURE;
        }

        $funcionario->update(['mamore_id' => $this->argument('mamore_id')]);

        $this->info("Funcionario '{$funcionario->usuario}' vinculado a mamore_id {$funcionario->mamore_id}.");

        return self::SUCCESS;
    }
}
