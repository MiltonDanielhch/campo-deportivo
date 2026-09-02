<?php

namespace App\Services;

use App\Models\Auditoria;

/**
 * Servicio genérico de bitácora de auditoría.
 *
 * Se construye UNA VEZ y se reutiliza en todo el proyecto:
 *   - Paramétricas (cambios de estado de campos, tarifas, funcionarios)
 *   - Confirmaciones de pago que llegan del Core de Recaudaciones
 *   - Cualquier operación que requiera trazabilidad
 *
 * No está acoplado a ningún dominio específico: solo recibe la tabla,
 * el id del registro, la acción y los snapshots antes/después.
 */
class AuditoriaService
{
    /**
     * Registra un evento de auditoría.
     *
     * @param string      $tabla           Nombre de la tabla afectada (ej. 'campos_deportivos')
     * @param string      $registroId      UUID o clave natural del registro afectado
     * @param string      $accion          Acción realizada (ej. 'cambiar_estado', 'crear_tarifa')
     * @param string|null $usuarioId       Funcionario responsable; NULL si fue un job automático
     * @param array|null  $datosAnteriores Snapshot del estado previo (jsonb)
     * @param array|null  $datosNuevos     Snapshot del estado posterior (jsonb)
     */
    public static function registrar(
        string $tabla,
        string $registroId,
        string $accion,
        ?string $usuarioId = null,
        ?array $datosAnteriores = null,
        ?array $datosNuevos = null,
    ): Auditoria {
        // Si no se pasó usuario explícito, intenta tomar el funcionario autenticado.
        // Esto mantiene el servicio genérico: funciona tanto en contexto HTTP como
        // en jobs (donde auth()->id() devuelve null y queda registrado como NULL).
        $usuarioId = $usuarioId ?? auth()->id();

        return Auditoria::create([
            'tabla' => $tabla,
            'registro_id' => $registroId,
            'accion' => $accion,
            'usuario_id' => $usuarioId,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
        ]);
    }
}
