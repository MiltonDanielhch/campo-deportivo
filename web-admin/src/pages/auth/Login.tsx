import { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { toast } from 'sonner';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/context/AuthContext';

export default function Login() {
  const navigate = useNavigate();
  const location = useLocation();
  const { login } = useAuth();
  const [usuario, setUsuario] = useState('');
  const [password, setPassword] = useState('');
  const [cargando, setCargando] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setCargando(true);

    try {
      await login(usuario, password);
      toast.success('Inicio de sesión exitoso');
      const from = (location.state as any)?.from?.pathname || '/panel';
      navigate(from, { replace: true });
    } catch (error: any) {
      const mensaje =
        error.response?.data?.message || 'Credenciales incorrectas o usuario inactivo';
      toast.error(mensaje);
    } finally {
      setCargando(false);
    }
  };

  return (
    <div className="grid min-h-svh lg:grid-cols-2">
      {/* ─── Panel izquierdo: branding institucional ─── */}
      <div className="relative hidden lg:flex flex-col justify-between p-12 bg-gradient-to-br from-emerald-700 via-emerald-800 to-emerald-900 text-white overflow-hidden">
        {/* Patrón decorativo sutil */}
        <div className="absolute inset-0 opacity-10">
          <svg className="w-full h-full" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" strokeWidth="1" />
              </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)" />
          </svg>
        </div>

        {/* Logo y título */}
        <div className="relative z-10">
          <div className="flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="h-6 w-6"
              >
                <circle cx="12" cy="12" r="10" />
                <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" />
                <path d="M2 12h20" />
              </svg>
            </div>
            <div>
              <p className="text-sm font-medium text-white/70">GAD Beni</p>
              <p className="text-lg font-bold">Campos Deportivos</p>
            </div>
          </div>
        </div>

        {/* Bloque central: tagline */}
        <div className="relative z-10 space-y-6">
          <h1 className="text-4xl font-bold leading-tight tracking-tight">
            Panel Administrativo
            <br />
            <span className="text-white/80">de alquiler de canchas</span>
          </h1>
          <p className="max-w-md text-lg text-white/80 leading-relaxed">
            Gestiona campos deportivos, horarios, tarifas y personal de control
            del Gobierno Autónomo Departamental del Beni.
          </p>

          <div className="grid grid-cols-3 gap-4 pt-6">
            <div className="rounded-xl bg-white/10 backdrop-blur-sm p-4">
              <p className="text-2xl font-bold">24/7</p>
              <p className="text-sm text-white/70">Disponibilidad</p>
            </div>
            <div className="rounded-xl bg-white/10 backdrop-blur-sm p-4">
              <p className="text-2xl font-bold">100%</p>
              <p className="text-sm text-white/70">Digital</p>
            </div>
            <div className="rounded-xl bg-white/10 backdrop-blur-sm p-4">
              <p className="text-2xl font-bold">GAD</p>
              <p className="text-sm text-white/70">Institucional</p>
            </div>
          </div>
        </div>

        {/* Pie de página del panel */}
        <div className="relative z-10">
          <p className="text-sm text-white/60">
            © {new Date().getFullYear()} Gobierno Autónomo Departamental del Beni
          </p>
        </div>
      </div>

      {/* ─── Panel derecho: formulario ─── */}
      <div className="flex flex-col justify-center p-6 sm:p-12 lg:p-20">
        {/* Logo en móvil (oculto en desktop porque ya está a la izquierda) */}
        <div className="flex lg:hidden items-center gap-3 mb-8">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-700 text-white">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              className="h-5 w-5"
            >
              <circle cx="12" cy="12" r="10" />
              <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" />
              <path d="M2 12h20" />
            </svg>
          </div>
          <div>
            <p className="text-xs font-medium text-muted-foreground">GAD Beni</p>
            <p className="text-base font-bold">Campos Deportivos</p>
          </div>
        </div>

        <div className="w-full max-w-sm mx-auto lg:mx-0 lg:max-w-md space-y-8">
          <div className="space-y-2">
            <h2 className="text-3xl font-bold tracking-tight">Iniciar sesión</h2>
            <p className="text-muted-foreground">
              Ingresa tus credenciales para acceder al panel administrativo
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-2">
              <Label htmlFor="usuario">Usuario</Label>
              <Input
                id="usuario"
                type="text"
                placeholder="admin"
                value={usuario}
                onChange={(e) => setUsuario(e.target.value)}
                required
                autoFocus
                autoComplete="username"
                className="h-11"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="password">Contraseña</Label>
              <Input
                id="password"
                type="password"
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                autoComplete="current-password"
                className="h-11"
              />
            </div>

            <Button
              type="submit"
              className="w-full h-11 text-base font-medium bg-emerald-700 hover:bg-emerald-800"
              disabled={cargando}
            >
              {cargando ? 'Iniciando sesión…' : 'Iniciar sesión'}
            </Button>
          </form>

          <p className="text-center text-xs text-muted-foreground pt-4">
            ¿Olvidaste tus credenciales? Contacta al administrador del sistema.
          </p>
        </div>
      </div>
    </div>
  );
}
