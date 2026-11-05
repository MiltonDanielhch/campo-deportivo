import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
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
      setError(data?.error === 'CREDENCIALES_INVALIDAS' ? 'CI o contraseña incorrectos.' : data?.message ?? 'No se pudo iniciar sesión.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <div className="container mx-auto max-w-md px-4 py-10">
      <h1 className="mb-1 text-2xl font-bold text-teal-800">Ingresar</h1>
      <p className="mb-6 text-sm text-gray-600">Accedé con tu CI para gestionar tu asociación.</p>
      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <label className="mb-1 block text-sm font-medium">CI</label>
          <input value={ci} onChange={(e) => setCi(e.target.value)} required inputMode="numeric"
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium">Contraseña</label>
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
        </div>
        {error && <p className="text-sm text-red-600">{error}</p>}
        <Button type="submit" disabled={enviando} className="w-full bg-teal-700 hover:bg-teal-800">
          {enviando ? 'Ingresando…' : 'Ingresar'}
        </Button>
        <p className="text-center text-sm text-gray-600">
          ¿No tenés cuenta? <Link to="/registro" className="font-medium text-teal-700 underline">Registrate</Link>
        </p>
      </form>
    </div>
  );
}
