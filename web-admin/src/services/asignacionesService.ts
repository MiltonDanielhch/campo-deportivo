import apiClient from './apiClient';
import type { CampoDeportivo } from '@/types/parametricas';
import type {
  AsignacionFuncionario,
  FuncionarioConAsignaciones,
} from '@/types/usuarios';

/**
 * Endpoints de asignación de campos a funcionarios (HU-B3).
 * Anidados bajo /v1/funcionarios/{id}/campos, más el listado global.
 */
export const asignacionesService = {
  /**
   * Campos asignados a un funcionario específico.
   */
  async porFuncionario(funcionarioId: string): Promise<CampoDeportivo[]> {
    const { data } = await apiClient.get<{ data: CampoDeportivo[] }>(
      `/v1/funcionarios/${funcionarioId}/campos`, // ✅ CORREGIDO: agregado /v1
    );
    return data.data;
  },

  /**
   * Asignar un campo a un funcionario.
   * El backend rechaza con 422 si el funcionario no es funcionario_control.
   */
  async asignar(
    funcionarioId: string,
    campoId: string,
  ): Promise<AsignacionFuncionario> {
    const { data } = await apiClient.post<{
      message: string;
      data: AsignacionFuncionario;
    }>(`/v1/funcionarios/${funcionarioId}/campos/${campoId}`); // ✅ CORREGIDO: agregado /v1
    return data.data;
  },

  /**
   * Desasignar un campo de un funcionario.
   */
  async desasignar(funcionarioId: string, campoId: string): Promise<void> {
    await apiClient.delete(`/v1/funcionarios/${funcionarioId}/campos/${campoId}`); // ✅ CORREGIDO: agregado /v1
  },

  /**
   * Funcionarios funcionario_control con al menos una asignación.
   */
  async todas(): Promise<FuncionarioConAsignaciones[]> {
    const { data } = await apiClient.get<{
      data: FuncionarioConAsignaciones[];
    }>('/v1/asignaciones'); // ✅ CORREGIDO: agregado /v1
    return data.data;
  },
};
