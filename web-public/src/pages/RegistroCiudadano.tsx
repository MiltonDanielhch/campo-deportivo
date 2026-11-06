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

export default function RegistroCiudadano() {
  const { registro } = useCiudadanoAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({
    ci: '',
    nombre_completo: '',
    telefono: '',
    password: '',
    password_confirmation: '',
  });
  const [error, setError] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setEnviando(true);
    try {
      await registro({ ...form, telefono: form.telefono || undefined });
      navigate('/mi-asociacion');
    } catch (err: any) {
      const data = err?.response?.data;
      const first = data?.errors ? Object.values(data.errors).flat()[0] : null;
      setError((first as string) ?? data?.message ?? 'No se pudo completar el registro.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div className="container mx-auto max-w-md px-4 py-10">
      <Card>
        <CardHeader>
          <CardTitle className="text-2xl text-teal-800 dark:text-teal-200">
            Crear cuenta
          </CardTitle>
          <CardDescription>
            Si tu CI ya está registrado como responsable de una asociación con
            convenio vigente, se vincula solo.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={onSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="ci">CI</Label>
              <Input
                id="ci"
                value={form.ci}
                onChange={set('ci')}
                required
                inputMode="numeric"
                autoComplete="username"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="nombre_completo">Nombre completo</Label>
              <Input
                id="nombre_completo"
                value={form.nombre_completo}
                onChange={set('nombre_completo')}
                required
                autoComplete="name"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="telefono">Teléfono (opcional)</Label>
              <Input
                id="telefono"
                value={form.telefono}
                onChange={set('telefono')}
                inputMode="tel"
                autoComplete="tel"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Contraseña</Label>
              <Input
                id="password"
                type="password"
                value={form.password}
                onChange={set('password')}
                required
                minLength={8}
                autoComplete="new-password"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
              <Input
                id="password_confirmation"
                type="password"
                value={form.password_confirmation}
                onChange={set('password_confirmation')}
                required
                autoComplete="new-password"
              />
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            <Button
              type="submit"
              disabled={enviando}
              className="w-full bg-teal-700 hover:bg-teal-800"
            >
              {enviando ? 'Creando…' : 'Crear cuenta'}
            </Button>
            <p className="text-center text-sm text-muted-foreground">
              ¿Ya tenés cuenta?{' '}
              <Link to="/login" className="font-medium text-teal-700 underline dark:text-teal-300">
                Ingresar
              </Link>
            </p>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
