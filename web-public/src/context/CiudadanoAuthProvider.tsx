import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { ciudadanoService, type Ciudadano } from '@/services/ciudadanoService';

const TOKEN_KEY = 'ciudadano_token';

interface Ctx {
  ciudadano: Ciudadano | null;
  cargandoSesion: boolean;
  login: (ci: string, password: string) => Promise<void>;
  registro: (p: {
    ci: string;
    nombre_completo: string;
    telefono?: string;
    password: string;
    password_confirmation: string;
  }) => Promise<void>;
  logout: () => Promise<void>;
}

const CiudadanoAuthContext = createContext<Ctx | null>(null);

export function CiudadanoAuthProvider({ children }: { children: ReactNode }) {
  const [ciudadano, setCiudadano] = useState<Ciudadano | null>(null);
  const [cargandoSesion, setCargandoSesion] = useState(true);

  // Restaurar sesion al montar: si hay token en localStorage, validar contra /me.
  // Si el token expiro/borro, me() da 401 -> limpiamos y quedamos deslogueado.
  useEffect(() => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (!token) {
      setCargandoSesion(false);
      return;
    }
    ciudadanoService
      .me()
      .then(setCiudadano)
      .catch(() => {
        localStorage.removeItem(TOKEN_KEY);
        setCiudadano(null);
      })
      .finally(() => setCargandoSesion(false));
  }, []);

  const login = async (ci: string, password: string) => {
    const { token, ciudadano: c } = await ciudadanoService.login({ ci, password });
    localStorage.setItem(TOKEN_KEY, token);
    setCiudadano(c);
  };

  const registro: Ctx['registro'] = async (p) => {
    const { token, ciudadano: c } = await ciudadanoService.registro(p);
    localStorage.setItem(TOKEN_KEY, token);
    setCiudadano(c);
  };

  const logout = async () => {
    try {
      await ciudadanoService.logout();
    } finally {
      localStorage.removeItem(TOKEN_KEY);
      setCiudadano(null);
    }
  };

  return (
    <CiudadanoAuthContext.Provider value={{ ciudadano, cargandoSesion, login, registro, logout }}>
      {children}
    </CiudadanoAuthContext.Provider>
  );
}

export function useCiudadanoAuth(): Ctx {
  const ctx = useContext(CiudadanoAuthContext);
  if (!ctx) throw new Error('useCiudadanoAuth debe usarse dentro de <CiudadanoAuthProvider>');
  return ctx;
}

/**
 * Variante NO-launching: devuelve null si el árbol no está envuelto en
 * <CiudadanoAuthProvider>. La usan páginas públicas (Reserva) que deben
 * degradar a anónimo sin romper el render si el provider no las envuelve.
 */
export function useCiudadanoAuthOpcional(): Ctx | null {
  return useContext(CiudadanoAuthContext);
}
