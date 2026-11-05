import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useCiudadanoAuth } from '@/context/CiudadanoAuthProvider';
import { ciudadanoService, type Alumno } from '@/services/ciudadanoService';

export default function MiAsociacion() {
  const { ciudadano, logout } = useCiudadanoAuth();
  const [alumnos, setAlumnos] = useState<Alumno[]>([]);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({ ci: '', nombre_completo: '', fecha_nacimiento: '' });
  const [guardando, setGuardando] = useState(false);

  const esAsociacion = !!ciudadano?.es_asociacion;

  useEffect(() => {
    if (!esAsociacion) return;
    setCargando(true);
    ciudadanoService
      .listarAlumnos()
      .then(setAlumnos)
      .catch(() => setError('No se pudo cargar la lista de alumnos.'))
      .finally(() => setCargando(false));
  }, [esAsociacion]);

  if (!ciudadano) return null;
  const a = ciudadano.asociacion;

  const cargar = async () => {
    const lista = await ciudadanoService.listarAlumnos();
    setAlumnos(lista);
  };

  const agregar = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setGuardando(true);
    try {
      await ciudadanoService.crearAlumno({
        ci: form.ci,
        nombre_completo: form.nombre_completo,
        fecha_nacimiento: form.fecha_nacimiento || undefined,
      });
      setForm({ ci: '', nombre_completo: '', fecha_nacimiento: '' });
      await cargar();
    } catch (err: any) {
      const data = err?.response?.data;
      const first = data?.errors ? Object.values(data.errors).flat()[0] : null;
      setError((first as string) ?? data?.message ?? 'No se pudo agregar el alumno.');
    } finally {
      setGuardando(false);
    }
  };

  const alternar = async (alumno: Alumno) => {
    setError(null);
    try {
      await ciudadanoService.desactivarAlumno(alumno.id, !alumno.activo);
      await cargar();
    } catch {
      setError('No se pudo cambiar el estado del alumno.');
    }
  };

  return (
    <div className="container mx-auto max-w-2xl px-4 py-10">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold text-teal-800">Mi asociación</h1>
        <Button variant="outline" size="sm" onClick={() => logout()}>Cerrar sesión</Button>
      </div>

      <div className="rounded-lg border border-gray-200 bg-white p-5">
        <p className="text-sm text-gray-500">{ciudadano.nombre_completo} · CI {ciudadano.ci}</p>
        {esAsociacion && a ? (
          <div className="mt-4 space-y-1">
            <p className="font-semibold text-teal-800">{a.nombre}</p>
            <p className="text-sm text-gray-600">Tipo: {a.tipo ?? '—'} · Convenio {a.convenio_numero ?? 's/n'}</p>
            <p className="text-sm text-gray-600">
              Vigencia: {a.convenio_hasta ?? '—'} · {a.vigente ? <span className="text-green-700">vigente</span> : <span className="text-amber-700">vencido</span>}
            </p>
          </div>
        ) : (
          <p className="mt-4 text-sm text-gray-600">
            Tu cuenta no está vinculada a ninguna asociación con convenio vigente. El convenio lo otorga el GAD al dar de alta la asociación.
          </p>
        )}
      </div>

      {esAsociacion && (
        <div className="mt-8 rounded-lg border border-gray-200 bg-white p-5">
          <h2 className="mb-1 text-lg font-semibold text-teal-800">Alumnos</h2>
          <p className="mb-4 text-sm text-gray-500">
            Cargá tu lista antes de ir a ventanilla. Al reservar, el funcionario elige quiénes asisten a cada franja.
          </p>

          <form onSubmit={agregar} className="mb-5 grid gap-3 sm:grid-cols-2">
            <input
              value={form.ci}
              onChange={(e) => setForm((f) => ({ ...f, ci: e.target.value }))}
              placeholder="CI del alumno"
              inputMode="numeric"
              required
              className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
            />
            <input
              value={form.nombre_completo}
              onChange={(e) => setForm((f) => ({ ...f, nombre_completo: e.target.value }))}
              placeholder="Nombre completo"
              required
              className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
            />
            <input
              type="date"
              value={form.fecha_nacimiento}
              onChange={(e) => setForm((f) => ({ ...f, fecha_nacimiento: e.target.value }))}
              max={new Date().toISOString().slice(0, 10)}
              className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none sm:col-span-2"
            />
            <Button type="submit" disabled={guardando} className="bg-teal-700 hover:bg-teal-800 sm:col-span-2">
              {guardando ? 'Agregando…' : 'Agregar alumno'}
            </Button>
          </form>

          {error && <p className="mb-3 text-sm text-red-600">{error}</p>}

          {cargando ? (
            <p className="text-sm text-gray-500">Cargando…</p>
          ) : alumnos.length === 0 ? (
            <p className="text-sm text-gray-500">Todavía no cargaste alumnos.</p>
          ) : (
            <ul className="divide-y divide-gray-100">
              {alumnos.map((al) => (
                <li key={al.id} className="flex items-center justify-between gap-3 py-2">
                  <div className="min-w-0">
                    <p className={`truncate text-sm font-medium ${al.activo ? '' : 'text-gray-400 line-through'}`}>
                      {al.nombre_completo}
                    </p>
                    <p className="truncate text-xs text-gray-500">
                      CI {al.ci}{al.fecha_nacimiento ? ` · nace ${al.fecha_nacimiento}` : ''}
                    </p>
                  </div>
                  <Button variant="outline" size="sm" onClick={() => alternar(al)}>
                    {al.activo ? 'Desactivar' : 'Reactivar'}
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
