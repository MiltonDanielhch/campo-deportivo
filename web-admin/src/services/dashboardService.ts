import apiClient from './apiClient';

export interface DistribucionHora {
  hora: number;
  total: number;
}

export interface DashboardResumen {
  fecha: string;
  reservas_hoy: number;
  campos_ocupados_ahora: number;
  ingresos_hoy: number;
  total_campos_activos: number;
  distribucion_hoy: DistribucionHora[];
  accesos_rapidos: string[];
}

export interface DashboardResponse {
  data: DashboardResumen;
}

export const dashboardService = {
  async resumen(): Promise<DashboardResponse> {
    const { data } = await apiClient.get<DashboardResponse>('/v1/dashboard/resumen');
    return data;
  },
};
