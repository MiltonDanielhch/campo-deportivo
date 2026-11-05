import { Button } from '@/components/ui/button';
import { useCiudadanoAuth } from '@/context/CiudadanoAuthProvider';

export default function MiAsociacion() {
  const { ciudadano, logout } = useCiudadanoAuth();
  if (!ciudadano) return null;
  const a = ciudadano.asociacion;

  return (
    <div className="container mx-auto max-w-2xl px-4 py-10">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold text-teal-800">Mi asociación</h1>
        <Button variant="outline" size="sm" onClick={() => logout()}>Cerrar sesión</Button>
      </div>

      <div className="rounded-lg border border-gray-200 bg-white p-5">
        <p className="text-sm text-gray-500">{ciudadano.nombre_completo} · CI {ciudadano.ci}</p>
        {ciudadano.es_asociacion && a ? (
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

      {/* Placeholder Fase 3: la UI de alumnos entra cuando teja los endpoints de asociacion_alumnos. */}
      <p className="mt-6 text-sm text-gray-500">Próximamente: carga de alumnos y agenda propia.</p>
    </div>
  );
}
