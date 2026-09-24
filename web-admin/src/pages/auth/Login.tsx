import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { useAuth } from "@/context/AuthContext";

export default function Login() {
  const { loginWithIbare } = useAuth();

  return (
    <div className="flex items-center justify-center min-h-screen bg-gray-50">
      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <CardTitle className="text-2xl font-bold">
            Campos Deportivos GAD Beni
          </CardTitle>
          <CardDescription>
            Panel de Administración
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col items-center gap-4">
          <p className="text-sm text-muted-foreground text-center mb-4">
            La autenticación es gestionada de forma centralizada por Ibare.
          </p>

          <Button
            onClick={loginWithIbare}
            className="w-full"
            size="lg"
          >
            Iniciar sesión con Ibare
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
