<?php

namespace App\Console\Commands;

use App\Models\CampoDeportivo;
use App\Models\TipoCampo;
use App\Services\CatalogoSirebService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vincula los campos deportivos locales con el catálogo oficial de SIREB.
 *
 * SIREB/Paitití es la fuente de verdad del catálogo: acá no se inventan
 * servicios, se toma lo que devuelve /api/v1/catalogo/servicios (el mismo
 * listado que se ve en /panel/servicios) y se refleja en campos_deportivos:
 *
 *   1. Completa servicio_sireb_id + servicio_sireb_codigo de los campos
 *      locales que ya tienen un equivalente en SIREB.
 *   2. Informa los servicios SIREB que no tienen campo local.
 *   3. Con --crear, da de alta el campo local faltante (requiere --tipo-campo).
 *
 * Es idempotente: correrlo dos veces no cambia nada la segunda vez.
 */
class MapearCamposSireb extends Command
{
    protected $signature = 'sireb:mapear-campos
        {--listar : Solo muestra el estado actual, sin proponer ni aplicar cambios}
        {--dry-run : Muestra los cambios que aplicaría, sin tocar la base}
        {--crear : Da de alta el campo local de los servicios SIREB que no tengan uno}
        {--tipo-campo= : UUID, código o nombre del tipo de campo para los campos que se creen}
        {--map=* : Pares extra codigo_local:codigo_sireb (repetible)}';

    protected $description = 'Vincula los campos locales con los servicios oficiales de SIREB';

    /**
     * Equivalencias conocidas entre el código interno del GAD y el código
     * de servicio de SIREB. Se pueden ampliar con --map=CD-010:SEDEDE-CS3.
     */
    private const MAPEO_POR_DEFECTO = [
        'CD-001' => 'SEDEDE-CS1',
        'FS-001' => 'SEDEDE-CS2',
        'CD-002' => 'SEDEDE-EGM-ENTREN',
        'CD-005' => '0005',
    ];

    public function handle(CatalogoSirebService $catalogoSireb): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $soloListar = (bool) $this->option('listar');

        try {
            $catalogo = $dryRun || $soloListar
                ? $catalogoSireb->catalogoCacheado()
                : $catalogoSireb->refrescarCatalogo();
        } catch (\Throwable $e) {
            $this->error('No se pudo obtener el catálogo de SIREB: '.$e->getMessage());
            $this->warn('Revisá RECAUDACIONES_API_URL, SIREB_CLIENT_ID y SIREB_CLIENT_SECRET en backend/.env');

            return self::FAILURE;
        }

        if (empty($catalogo)) {
            $this->error('SIREB devolvió un catálogo vacío. No hay nada que vincular.');

            return self::FAILURE;
        }

        $serviciosPorCodigo = collect($catalogo)->keyBy(fn ($s) => (string) ($s['codigo'] ?? ''));
        $serviciosPorId = collect($catalogo)->keyBy(fn ($s) => (string) ($s['id'] ?? ''));

        $campos = CampoDeportivo::orderBy('codigo')->get();

        $this->info('Catálogo SIREB: '.count($catalogo).' servicios · Campos locales: '.$campos->count());

        if ($soloListar) {
            $this->listarEstado($campos, $catalogo);

            return self::SUCCESS;
        }

        $mapa = $this->mapaDeVinculacion();

        $filas = [];
        $idsUsados = $campos->pluck('servicio_sireb_id')->filter()->all();
        $codigosUsados = [];

        foreach ($campos as $campo) {
            [$servicio, $origen] = $this->resolverServicio($campo, $mapa, $serviciosPorCodigo, $serviciosPorId);

            if (! $servicio) {
                $filas[] = [
                    $campo->codigo,
                    $campo->nombre,
                    '—',
                    $campo->servicio_sireb_id
                        ? 'Se mantiene el vínculo actual (no está en el catálogo de hoy)'
                        : 'Sin equivalente en SIREB',
                ];

                continue;
            }

            $codigoSireb = (string) $servicio['codigo'];
            $idSireb = (string) $servicio['id'];

            if (isset($codigosUsados[$codigoSireb])) {
                $filas[] = [$campo->codigo, $campo->nombre, $codigoSireb, '⚠ ya asignado a '.$codigosUsados[$codigoSireb].' — se omite'];

                continue;
            }

            if ($campo->servicio_sireb_id && $campo->servicio_sireb_id !== $idSireb
                && $campo->servicio_sireb_codigo === $codigoSireb) {
                // Mismo servicio con UUID distinto (p. ej. recreado en SIREB).
                $filas[] = [$campo->codigo, $campo->nombre, $codigoSireb, $dryRun ? 'DIFIERE (dry-run)' : 'UUID actualizado'];

                if (! $dryRun) {
                    $campo->update(['servicio_sireb_id' => $idSireb, 'servicio_sireb_codigo' => $codigoSireb]);
                }

                $codigosUsados[$codigoSireb] = $campo->codigo;

                continue;
            }

            if ($campo->servicio_sireb_id === $idSireb && $campo->servicio_sireb_codigo === $codigoSireb) {
                $filas[] = [$campo->codigo, $campo->nombre, $codigoSireb, 'OK'];
                $codigosUsados[$codigoSireb] = $campo->codigo;

                continue;
            }

            if (in_array($idSireb, $idsUsados, true) && $campo->servicio_sireb_id !== $idSireb) {
                $filas[] = [$campo->codigo, $campo->nombre, $codigoSireb, '⚠ servicio ya vinculado a otro campo — se omite'];

                continue;
            }

            $yaVinculado = (bool) $campo->servicio_sireb_id;

            $estado = match (true) {
                $origen === 'mapa' && ! $yaVinculado => $dryRun ? 'VINCULAR (dry-run)' : 'Vinculado',
                $origen === 'mapa' && $yaVinculado => $dryRun ? 'REVINCULAR (dry-run)' : 'Revincular',
                $origen === 'nombre' => $dryRun ? 'VINCULAR POR NOMBRE (dry-run)' : 'Vinculado por nombre',
                $yaVinculado => $dryRun ? 'COMPLETAR CÓDIGO (dry-run)' : 'Código completado',
                default => $dryRun ? 'VINCULAR (dry-run)' : 'Vinculado',
            };

            $filas[] = [$campo->codigo, $campo->nombre, $codigoSireb, $estado];

            if (! $dryRun) {
                $campo->update(['servicio_sireb_id' => $idSireb, 'servicio_sireb_codigo' => $codigoSireb]);
            }

            $idsUsados[] = $idSireb;
            $codigosUsados[$codigoSireb] = $campo->codigo;
        }

        $this->newLine();
        $this->info('VINCULACIÓN CAMPOS LOCALES → SIREB');
        $this->table(['Campo', 'Nombre local', 'Servicio SIREB', 'Acción'], $filas);

        $sinCampoLocal = collect($catalogo)
            ->reject(fn ($s) => isset($codigosUsados[(string) $s['codigo']]))
            ->reject(fn ($s) => $campos->contains('servicio_sireb_id', $s['id']));

        if ($sinCampoLocal->isNotEmpty()) {
            $this->newLine();
            $this->info('SERVICIOS SIREB SIN CAMPO LOCAL');

            $filasFaltantes = $sinCampoLocal->map(fn ($s) => [
                $s['codigo'] ?? '—',
                $s['nombre'] ?? '—',
                $s['estado'] ?? '—',
                $this->rangoPrecios($s),
            ])->values()->all();

            $this->table(['Código', 'Nombre', 'Estado', 'Precio'], $filasFaltantes);

            if ($this->option('crear')) {
                $this->crearCamposFaltantes($sinCampoLocal, $dryRun);
            } else {
                $this->warn('Ninguno de estos servicios se publica hasta que exista un campo local. Volvé a correr con --crear.');
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('Dry-run: no se escribió ningún cambio en la base.');
        } else {
            $this->info('Listo. El catálogo público ya muestra el nombre y el código oficiales de SIREB.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CampoDeportivo>  $campos
     * @param  array<int, array<string, mixed>>  $catalogo
     */
    private function listarEstado($campos, array $catalogo): void
    {
        $this->newLine();
        $this->info('CAMPOS LOCALES');
        $this->table(
            ['Código', 'Nombre local', 'SIREB ID', 'SIREB Código', 'Estado'],
            $campos->map(fn (CampoDeportivo $c) => [
                $c->codigo,
                $c->nombre,
                $c->servicio_sireb_id ? substr($c->servicio_sireb_id, 0, 8).'…' : '—',
                $c->servicio_sireb_codigo ?? '—',
                $c->servicio_sireb_id ? 'Vinculado' : 'Sin vincular',
            ])->all()
        );

        $this->newLine();
        $this->info('SERVICIOS SIREB (panel /panel/servicios)');
        $this->table(
            ['Código', 'Nombre', 'Estado', 'Tarifas', 'Precio'],
            collect($catalogo)->map(fn ($s) => [
                $s['codigo'] ?? '—',
                $s['nombre'] ?? '—',
                $s['estado'] ?? '—',
                count($s['tarifas'] ?? []),
                $this->rangoPrecios($s),
            ])->all()
        );
    }

    /**
     * @return array<string, string> codigo_local => codigo_sireb
     */
    private function mapaDeVinculacion(): array
    {
        $mapa = self::MAPEO_POR_DEFECTO;

        foreach ($this->option('map') as $par) {
            if (! str_contains($par, ':')) {
                $this->warn("Par --map ignorado (formato codigo_local:codigo_sireb): {$par}");

                continue;
            }

            [$local, $sireb] = explode(':', $par, 2);
            $mapa[trim($local)] = trim($sireb);
        }

        return $mapa;
    }

    /**
     * Resuelve a qué servicio SIREB corresponde un campo local.
     *
     * Prioridad: vínculo ya guardado > mapa explícito > coincidencia de nombre.
     *
     * @param  array<string, string>  $mapa
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $serviciosPorCodigo
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $serviciosPorId
     * @return array{0: array<string, mixed>|null, 1: string}
     */
    private function resolverServicio(
        CampoDeportivo $campo,
        array $mapa,
        $serviciosPorCodigo,
        $serviciosPorId
    ): array {
        if ($campo->servicio_sireb_codigo && $serviciosPorCodigo->has($campo->servicio_sireb_codigo)) {
            return [$serviciosPorCodigo->get($campo->servicio_sireb_codigo), 'vinculo'];
        }

        if ($campo->servicio_sireb_id && $serviciosPorId->has($campo->servicio_sireb_id)) {
            return [$serviciosPorId->get($campo->servicio_sireb_id), 'vinculo'];
        }

        if (isset($mapa[$campo->codigo]) && $serviciosPorCodigo->has($mapa[$campo->codigo])) {
            return [$serviciosPorCodigo->get($mapa[$campo->codigo]), 'mapa'];
        }

        $porNombre = $this->buscarPorNombre($campo->nombre, $serviciosPorCodigo);

        if ($porNombre) {
            return [$porNombre, 'nombre'];
        }

        return [null, 'ninguno'];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $serviciosPorCodigo
     * @return array<string, mixed>|null
     */
    private function buscarPorNombre(string $nombreLocal, $serviciosPorCodigo): ?array
    {
        $normalizar = fn (string $v) => preg_replace('/[^a-z0-9]+/', '', mb_strtolower($v));
        $buscado = $normalizar($nombreLocal);

        if ($buscado === '') {
            return null;
        }

        foreach ($serviciosPorCodigo as $servicio) {
            $nombreServicio = $normalizar((string) ($servicio['nombre'] ?? ''));

            if ($nombreServicio === '') {
                continue;
            }

            if (str_contains($nombreServicio, $buscado) || str_contains($buscado, $nombreServicio)) {
                return $servicio;
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $servicios
     */
    private function crearCamposFaltantes($servicios, bool $dryRun): void
    {
        $tipoCampo = $this->resolverTipoCampo();

        if (! $tipoCampo) {
            $this->error('Falta --tipo-campo: hace falta un tipo de campo local para dar de alta los servicios faltantes.');

            return;
        }

        $this->newLine();
        $this->info("Alta de campos locales (tipo: {$tipoCampo->nombre})");

        foreach ($servicios as $servicio) {
            $codigo = (string) $servicio['codigo'];
            $codigoLocal = 'SIR-'.$codigo;

            if (CampoDeportivo::where('codigo', $codigoLocal)->exists()) {
                $this->line("  · {$codigoLocal} ya existe — se omite");

                continue;
            }

            if ($dryRun) {
                $this->line("  · [dry-run] crearía {$codigoLocal} — {$servicio['nombre']}");

                continue;
            }

            DB::transaction(function () use ($servicio, $tipoCampo, $codigoLocal) {
                CampoDeportivo::create([
                    'tipo_campo_id' => $tipoCampo->id,
                    'codigo' => $codigoLocal,
                    'nombre' => $servicio['nombre'],
                    'direccion' => 'A definir',
                    'latitud' => -14.8333,
                    'longitud' => -64.9,
                    'estado' => 'activo',
                    'servicio_sireb_id' => $servicio['id'],
                    'servicio_sireb_codigo' => $servicio['codigo'],
                ]);
            });

            $this->line("  ✓ {$codigoLocal} creado y vinculado a {$servicio['codigo']}");
        }
    }

    private function resolverTipoCampo(): ?TipoCampo
    {
        $valor = $this->option('tipo-campo');

        if (! $valor) {
            return null;
        }

        return TipoCampo::query()
            ->where(fn ($q) => $q
                ->where('id', $valor)
                ->orWhereRaw('LOWER(nombre) = ?', [mb_strtolower($valor)])
            )
            ->first();
    }

    /**
     * @param  array<string, mixed>  $servicio
     */
    private function rangoPrecios(array $servicio): string
    {
        $precios = collect($servicio['tarifas'] ?? [])
            ->pluck('monto')
            ->filter()
            ->map(fn ($m) => (float) $m)
            ->values();

        if ($precios->isEmpty()) {
            return '—';
        }

        return $precios->min() === $precios->max()
            ? 'Bs. '.number_format($precios->min(), 2)
            : 'Bs. '.number_format($precios->min(), 2).' – Bs. '.number_format($precios->max(), 2);
    }
}
