import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { useCiudadanoAuth } from '@/context/CiudadanoAuthProvider';

export default function RegistroCiudadano() {
  const { registro } = useCiudadanoAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ ci: '', nombre_completo: '', telefono: '', password: '', password_confirmation: '' });
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
      <h1 className="mb-1 text-2xl font-bold text-teal-800">Crear cuenta</h1>
      <p className="mb-6 text-sm text-gray-600">
        Si tu CI ya está registrado como responsable de una asociación con convenio vigente, se vincula solo.
      </p>
      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <label className="mb-1 block text-sm font-medium">CI</label>
          <input value={form.ci} onChange={set('ci')} required inputMode="numeric"
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium">Nombre completo</label>
          <input value={form.nombre_completo} onChange={set('nombre_completo')} required
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium">Teléfono (opcional)</label>
          <input value={form.telefono} onChange={set('telefono')} inputMode="tel"
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium">Contraseña</label>
          <input type="password" value={form.password} onChange={set('password')} required minLength={8}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium">Confirmar contraseña</label>
          <input type="password" value={form.password_confirmation} onChange={set('password_confirmation')} required
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
        </div>
        {error && <p className="text-sm text-red-600">{error}</p>}
        <Button type="submit" disabled={enviando} className="w-full bg-teal-700 hover:bg-teal-800">
          {enviando ? 'Creando…' : 'Crear cuenta'}
        </Button>
        <p className="text-center text-sm text-gray-600">
          ¿Ya tenés cuenta? <Link to="/login" className="font-medium text-teal-700 underline">Ingresar</Link>
        </p>
      </form>
    </div>
  );
}
