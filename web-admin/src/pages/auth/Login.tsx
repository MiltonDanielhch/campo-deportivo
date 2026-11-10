import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { KeyRound, LogIn, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/context/AuthContext';

export default function Login() {
  const navigate = useNavigate();
  const { loginWithIbare, loginLocal } = useAuth();

  const [modoEmergencia, setModoEmergencia] = useState(false);
  const [usuario, setUsuario] = useState('');
  const [password, setPassword] = useState('');
  const [enviando, setEnviando] = useState(false);

  const submitEmergencia = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!usuario.trim() || !password) {
      toast.error('Ingresá usuario y contraseña.');
      return;
    }

    setEnviando(true);

    try {
      await loginLocal(usuario.trim(), password);
      toast.success('Acceso de emergencia iniciado');
      navigate('/panel', { replace: true });
    } catch (error: any) {
      toast.error(
        error.response?.data?.message ||
          'Credenciales inválidas o acceso de emergencia no disponible',
      );
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-4">
      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <CardTitle className="text-2xl font-bold">
            Campos Deportivos GAD Beni
          </CardTitle>
          <CardDescription>Panel de Administración</CardDescription>
        </CardHeader>

        <CardContent className="flex flex-col gap-4">
          {!modoEmergencia ? (
            <>
              <p className="text-sm text-muted-foreground text-center">
                La autenticación principal es gestionada por Ibare.
              </p>

              <Button onClick={loginWithIbare} className="w-full" size="lg">
                <LogIn className="mr-2 size-4" />
                Iniciar sesión con Ibare
              </Button>

              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setModoEmergencia(true)}
                className="text-xs text-muted-foreground"
              >
                <ShieldAlert className="mr-2 size-3.5" />
                ¿Ibare no responde? Acceso de emergencia
              </Button>
            </>
          ) : (
            <form onSubmit={submitEmergencia} className="space-y-4">
              <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                Acceso restringido a cuentas de emergencia habilitadas.
                Cada intento queda auditado.
              </div>

              <div className="space-y-2">
                <Label htmlFor="emergencia-usuario">Usuario</Label>
                <Input
                  id="emergencia-usuario"
                  value={usuario}
                  onChange={(e) => setUsuario(e.target.value)}
                  placeholder="admin"
                  autoComplete="username"
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="emergencia-password">Contraseña</Label>
                <Input
                  id="emergencia-password"
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  autoComplete="current-password"
                  required
                />
              </div>

              <Button type="submit" className="w-full" disabled={enviando}>
                <KeyRound className="mr-2 size-4" />
                {enviando ? 'Verificando…' : 'Entrar en emergencia'}
              </Button>

              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setModoEmergencia(false)}
                className="w-full text-xs text-muted-foreground"
              >
                Volver a Ibare
              </Button>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
