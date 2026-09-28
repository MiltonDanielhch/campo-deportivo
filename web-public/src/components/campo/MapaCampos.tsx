import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';
import { Icon, LatLngExpression } from 'leaflet';
import { useNavigate } from 'react-router-dom';
import { Sun, Lightbulb, MapPin } from 'lucide-react';

interface Campo {
  id: string;
  nombre: string;
  tipo_campo: { nombre: string };
  direccion: string;
  estado: string;
  latitud: number;
  longitud: number;
  imagen_url: string | null;
  tarifas: {
    diurna: { precio_por_hora: number } | null;
    nocturna: { precio_por_hora: number } | null;
  };
}

interface MapaCamposProps {
  campos: Campo[];
}

const iconoActivo = new Icon({
  iconUrl:
    'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
  shadowUrl:
    'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

const iconoMantenimiento = new Icon({
  iconUrl:
    'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-orange.png',
  shadowUrl:
    'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

export default function MapaCampos({ campos }: MapaCamposProps) {
  const navigate = useNavigate();
  const centroTrinidad: LatLngExpression = [-14.84, -64.9];

  return (
    <MapContainer
      center={centroTrinidad}
      zoom={13}
      className="h-[500px] md:h-[600px] w-full"
      scrollWheelZoom={false}
    >
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      {campos.map((campo) => {
        const { diurna, nocturna } = campo.tarifas ?? {
          diurna: null,
          nocturna: null,
        };

        return (
          <Marker
            key={campo.id}
            position={[campo.latitud, campo.longitud]}
            icon={campo.estado === 'activo' ? iconoActivo : iconoMantenimiento}
            eventHandlers={{
              click: () => {
                if (campo.estado === 'activo') {
                  navigate(`/campos/${campo.id}`);
                }
              },
            }}
          >
            <Popup>
              <div className="min-w-[200px] p-1">
                <p className="text-xs uppercase tracking-wider text-slate-500 mb-1">
                  {campo.tipo_campo.nombre}
                </p>
                <p className="font-bold text-base mb-1">{campo.nombre}</p>
                <p className="flex items-start gap-1 text-xs text-slate-600 mb-2">
                  <MapPin className="w-3 h-3 mt-0.5 flex-shrink-0" />
                  <span>{campo.direccion}</span>
                </p>

                {/* Precios */}
                <div className="flex items-center gap-3 pt-2 border-t border-slate-100">
                  {diurna && (
                    <div className="flex items-center gap-1 text-xs">
                      <Sun className="w-3 h-3 text-amber-500" />
                      <span className="font-semibold">
                        Bs {diurna.precio_por_hora.toFixed(0)}
                      </span>
                    </div>
                  )}
                  {nocturna && (
                    <div className="flex items-center gap-1 text-xs">
                      <Lightbulb className="w-3 h-3 text-indigo-500" />
                      <span className="font-semibold">
                        Bs {nocturna.precio_por_hora.toFixed(0)}
                      </span>
                    </div>
                  )}
                  {!diurna && !nocturna && (
                    <span className="text-xs text-slate-400 italic">
                      Consultar precio
                    </span>
                  )}
                </div>

                {campo.estado === 'mantenimiento' && (
                  <p className="text-orange-600 text-xs mt-2 font-medium">
                    En mantenimiento
                  </p>
                )}

                {campo.estado === 'activo' && (
                  <p className="text-teal-600 text-xs mt-2 font-medium">
                    Click para ver disponibilidad →
                  </p>
                )}
              </div>
            </Popup>
          </Marker>
        );
      })}
    </MapContainer>
  );
}
