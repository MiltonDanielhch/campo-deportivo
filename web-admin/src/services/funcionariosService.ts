import apiClient from './apiClient';
import type { PaginatedResponse } from '@/types/parametricas';
import type {
  CambioEstadoFuncionarioPayload,
  Funcionario,
  FuncionarioPayload,
  FuncionarioUpdatePayload,
  Rol,
} from '@/types/usuarios';


// ✅ CORREGIDO: agregado /v1 al inicio
const BASE = '/v1/funcionarios';

export const funcionariosService = {
  /**
   * Listado paginado con filtros opcionales por rol_id y estado.
   */
  async listar(params?: { rol_id?: string; estado?: string; page?: number }) {
    const { data } = await apiClient.get<PaginatedResponse<Funcionario>>(
      BASE,
      { params },
    );
    return data;
  },

  /**
   * Detalle de un funcionario.
   */
  async obtener(id: string): Promise<Funcionario> {
    const { data } = await apiClient.get<{ data: Funcionario }>(`${BASE}/${id}`);
    return data.data;
  },

  /**
   * Crear un funcionario nuevo (contraseña se hashea en el backend).
   */
  async crear(payload: FuncionarioPayload): Promise<Funcionario> {
    const { data } = await apiClient.post<{
      message: string;
      data: Funcionario;
    }>(BASE, payload);
    return data.data;
  },

  /**
   * Actualizar datos generales (contraseña opcional).
   */
  async actualizar(
    id: string,
    payload: FuncionarioUpdatePayload,
  ): Promise<Funcionario> {
    const { data } = await apiClient.put<{
      message: string;
      data: Funcionario;
    }>(`${BASE}/${id}`, payload);
    return data.data;
  },

  /**
   * Activar o inactivar un funcionario (sin eliminarlo).
   */
  async cambiarEstado(
    id: string,
    payload: CambioEstadoFuncionarioPayload,
  ): Promise<Funcionario> {
    const { data } = await apiClient.patch<{
      message: string;
      data: Funcionario;
    }>(`${BASE}/${id}/estado`, payload);
    return data.data;
  },

    /**
   * Lista los roles disponibles para poblar el select de alta.
   * (Endpoint auxiliar necesario para la pantalla de Funcionarios.)
   */
  async listarRoles(): Promise<Rol[]> {
    // ✅ CORREGIDO: agregado /v1 al inicio
    const { data } = await apiClient.get<{ data: Rol[] }>('/v1/roles');
    return data.data;
  },
};
