import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import apiClient from '@/services/apiClient';

export interface Rol {
  id: string;
  nombre: string;
  descripcion: string | null;
  permisos: string[] | null;
}

export interface Funcionario {
  id: string;
  nombre_completo: string;
  ci: string;
  usuario: string;
  estado: 'activo' | 'inactivo';
  rol: Rol;
  creado_en: string;
}

interface AuthState {
  funcionario: Funcionario | null;
  cargando: boolean;
}

interface AuthContextValue extends AuthState {
  loginWithIbare: () => void; // Cambiado: ahora es una redirección
  logout: () => Promise<void>;
  tienePermiso: (permiso: string) => boolean;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({
    funcionario: null,
    cargando: true,
  });

  // Al montar: intentar obtener la sesión desde la cookie
  useEffect(() => {
    apiClient
      .get<{ data: Funcionario }>('/v1/auth/me') // ✅ CORREGIDO: agregado /v1 al inicio
      .then((res) => {
        setState({ funcionario: res.data.data, cargando: false });
      })
      .catch(() => {
        // No hay sesión válida (401), limpiar estado y dejar de cargar
        setState({ funcionario: null, cargando: false });
      });
  }, []);

  // Redirige al backend de Laravel, que a su vez redirige a Ibare
  const loginWithIbare = () => {
    window.location.href = 'http://localhost:8000/auth/login-redirect';
  };

  const logout = async () => {
    try {
      // Llama al backend para limpiar la sesión
      await apiClient.post('/v1/auth/logout');
    } catch (error) {
      console.error('Error al cerrar sesión en el backend', error);
    } finally {
      // Limpiamos el estado local y redirigimos
      setState({ funcionario: null, cargando: false });
      window.location.href = '/login';
    }
  };

  const tienePermiso = (permiso: string): boolean => {
    const permisos = state.funcionario?.rol?.permisos ?? [];
    return permisos.includes('*') || permisos.includes(permiso);
  };

  return (
    <AuthContext.Provider value={{ ...state, loginWithIbare, logout, tienePermiso }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth debe usarse dentro de un <AuthProvider>');
  }
  return ctx;
}
