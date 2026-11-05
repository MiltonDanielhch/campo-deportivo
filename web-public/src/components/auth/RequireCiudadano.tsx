import { Navigate } from 'react-router-dom';
import { useCiudadanoAuth } from '@/context/CiudadanoAuthProvider';

export default function RequireCiudadano({ children }: { children: React.ReactNode }) {
  const { ciudadano, cargandoSesion } = useCiudadanoAuth();

  if (cargandoSesion) {
    return <div className="flex min-h-[50vh] items-center justify-center text-sm text-muted-foreground">Cargando…</div>;
  }
  if (!ciudadano) return <Navigate to="/login" replace />;
  return <>{children}</>;
}
