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
  login: (usuario: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  tienePermiso: (permiso: string) => boolean;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

const TOKEN_KEY = 'auth_token';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({
    funcionario: null,
    cargando: true,
  });

  // Al montar: si hay token guardado, restaurar sesión
  useEffect(() => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (!token) {
      setState({ funcionario: null, cargando: false });
      return;
    }

    apiClient
      .get<{ funcionario: Funcionario }>('/auth/me')
      .then((res) => {
        setState({ funcionario: res.data.funcionario, cargando: false });
      })
      .catch(() => {
        // Token inválido/expirado: limpiar y dejar sin sesión
        localStorage.removeItem(TOKEN_KEY);
        setState({ funcionario: null, cargando: false });
      });
  }, []);

  const login = async (usuario: string, password: string) => {
    const res = await apiClient.post<{
      token: string;
      funcionario: Funcionario;
    }>('/auth/login', { usuario, password });

    localStorage.setItem(TOKEN_KEY, res.data.token);
    setState({ funcionario: res.data.funcionario, cargando: false });
  };

  const logout = async () => {
    try {
      await apiClient.post('/auth/logout');
    } finally {
      localStorage.removeItem(TOKEN_KEY);
      setState({ funcionario: null, cargando: false });
      // Redirige al login tras logout
      window.location.href = '/login';
    }
  };

  const tienePermiso = (permiso: string): boolean => {
    const permisos = state.funcionario?.rol?.permisos ?? [];
    return permisos.includes('*') || permisos.includes(permiso);
  };

  return (
    <AuthContext.Provider value={{ ...state, login, logout, tienePermiso }}>
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
