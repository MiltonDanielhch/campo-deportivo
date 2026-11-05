import { api } from '@/lib/api';

export interface AsociacionPerfil {
  id: string;
  nombre: string;
  tipo: string | null;
  convenio_numero: string | null;
  convenio_hasta: string | null;
  vigente: boolean;
}

export interface Ciudadano {
  id: string;
  ci: string;
  nombre_completo: string;
  telefono: string | null;
  es_asociacion: boolean;
  asociacion: AsociacionPerfil | null;
}

export interface AuthResponse {
  token: string;
  ciudadano: Ciudadano;
}

export interface Alumno {
  id: string;
  ci: string;
  nombre_completo: string;
  fecha_nacimiento: string | null;
  activo: boolean;
}

// baseURL de api.ts ya incluye /api/v1, entonces las rutas van sin el prefijo.
export const ciudadanoService = {
  registro: async (p: {
    ci: string;
    nombre_completo: string;
    telefono?: string;
    password: string;
    password_confirmation: string;
  }): Promise<AuthResponse> => (await api.post('/ciudadanos/auth/registro', p)).data.data,

  login: async (p: { ci: string; password: string }): Promise<AuthResponse> =>
    (await api.post('/ciudadanos/auth/login', p)).data.data,

  me: async (): Promise<Ciudadano> => (await api.get('/ciudadanos/me')).data.data,

  logout: async (): Promise<void> => {
    await api.post('/ciudadanos/auth/logout');
  },

  // ─── Fase 3 paso 1: catalogo de alumnos (presidente) ───
  // El interceptor de api.ts ya adjunta el token del presidente logueado,
  // entonces estas llamadas llevan Authorization sin tocar nada mas.
  listarAlumnos: async (): Promise<Alumno[]> =>
    (await api.get('/ciudadanos/alumnos')).data.data,

  crearAlumno: async (p: {
    ci: string;
    nombre_completo: string;
    fecha_nacimiento?: string;
  }): Promise<Alumno> => (await api.post('/ciudadanos/alumnos', p)).data.data,

  actualizarAlumno: async (
    id: string,
    p: { ci: string; nombre_completo: string; fecha_nacimiento?: string },
  ): Promise<Alumno> => (await api.put(`/ciudadanos/alumnos/${id}`, p)).data.data,

  desactivarAlumno: async (id: string, activo: boolean): Promise<Alumno> =>
    (await api.patch(`/ciudadanos/alumnos/${id}/estado`, { activo })).data.data,
};
