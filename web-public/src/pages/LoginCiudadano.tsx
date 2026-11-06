import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { useCiudadanoAuth } from '@/context/CiudadanoAuthProvider';

export default function LoginCiudadano() {
  const { login } = useCiudadanoAuth();
  const navigate = useNavigate();
  const [ci, setCi] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setEnviando(true);
    try {
      await login(ci, password);
      navigate('/mi-asociacion');
    } catch (err: any) {
      const data = err?.response?.data;
      setError(
        data?.error === 'CREDENCIALES_INVALIDAS'
          ? 'CI o contraseña incorrectos.'
          : data?.message ?? 'No se pudo iniciar sesión.',
      );
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div className="container mx-auto max-w-md px-4 py-10">
      <Card>
        <CardHeader>
          <CardTitle className="text-2xl text-teal-800 dark:text-teal-200">
            Ingresar
          </CardTitle>
          <CardDescription>
            Accedé con tu CI para gestionar tu asociación.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={onSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="ci">CI</Label>
              <Input
                id="ci"
                value={ci}
                onChange={(e) => setCi(e.target.value)}
                required
                inputMode="numeric"
                autoComplete="username"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Contraseña</Label>
              <Input
                id="password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                autoComplete="current-password"
              />
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            <Button
              type="submit"
              disabled={enviando}
              className="w-full bg-teal-700 hover:bg-teal-800"
            >
              {enviando ? 'Ingresando…' : 'Ingresar'}
            </Button>
            <p className="text-center text-sm text-muted-foreground">
              ¿No tenés cuenta?{' '}
              <Link to="/registro" className="font-medium text-teal-700 underline dark:text-teal-300">
                Registrate
              </Link>
            </p>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
