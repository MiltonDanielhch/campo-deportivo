import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';
import { Icon, LatLngExpression } from 'leaflet';
import { useNavigate } from 'react-router-dom';

interface Campo {
  id: string;
  nombre: string;
  tipo_campo: { nombre: string };
  direccion: string;
  estado: string;
  latitud: number;
  longitud: number;
}

interface MapaCamposProps {
  campos: Campo[];
}

// Iconos personalizados según estado
const iconoActivo = new Icon({
  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

const iconoMantenimiento = new Icon({
  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-orange.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

export default function MapaCampos({ campos }: MapaCamposProps) {
  const navigate = useNavigate();
  const centroTrinidad: LatLngExpression = [-14.84, -64.90];

  return (
    <MapContainer
      center={centroTrinidad}
      zoom={13}
      className="h-[400px] w-full rounded-lg"
      scrollWheelZoom={false}
    >
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      {campos.map((campo) => (
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
            <div className="text-sm">
              <p className="font-semibold">{campo.nombre}</p>
              <p className="text-gray-600">{campo.tipo_campo.nombre}</p>
              <p className="text-xs">{campo.direccion}</p>
              {campo.estado === 'mantenimiento' && (
                <p className="text-orange-600 text-xs mt-1">En mantenimiento</p>
              )}
            </div>
          </Popup>
        </Marker>
      ))}
    </MapContainer>
  );
}