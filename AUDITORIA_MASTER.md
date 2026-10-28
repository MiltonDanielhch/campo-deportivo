# 🛠️ Auditoría de Software — campos-deportivos-gad-beni

> Generado: `2026-10-27 19:54`

## Resumen

| Métrica | Valor |
| :--- | :--- |
| **Proyecto** | `campos-deportivos-gad-beni` |
| **Líneas de Código (Netas)** | 80118 LoC |
| **Peso Total del Proyecto** | 936.89MB |
| **Archivos analizados** | 477 |
| **Timestamp** | 2026-10-27 19:54 |
| **Estado** | Activa |

## Breakdown por Capa

| Capa | Archivos | LoC | Comentarios | Peso | % LoC |
| :--- | ---: | ---: | ---: | ---: | ---: |
| `src` | 93 | 19507 | 0 | 733.07KB | 24.3% ████ |
| `app` | 123 | 12590 | 0 | 516.20KB | 15.7% ███ |
| `web-public` | 13 | 10620 | 13 | 368.42KB | 13.3% ██ |
| `backend` | 14 | 9122 | 4 | 377.86KB | 11.4% ██ |
| `tests` | 44 | 8216 | 0 | 343.25KB | 10.3% ██ |
| `web-admin` | 15 | 7890 | 8 | 274.04KB | 9.8% █ |
| `src` | 50 | 6773 | 0 | 270.62KB | 8.5% █ |
| `database` | 57 | 1987 | 0 | 76.97KB | 2.5%  |
| `config` | 13 | 1307 | 0 | 50.48KB | 1.6%  |
| `bootstrap` | 4 | 364 | 0 | 24.78KB | 0.5%  |
| `routes` | 3 | 293 | 0 | 18.07KB | 0.4%  |
| `storage` | 12 | 246 | 0 | 2.06MB | 0.3%  |
| `resources` | 3 | 213 | 0 | 70.96KB | 0.3%  |
| `docker` | 4 | 126 | 0 | 3.90KB | 0.2%  |
| `scripts` | 1 | 60 | 0 | 2.30KB | 0.1%  |
| `public` | 13 | 36 | 0 | 1.98MB | 0.0%  |
| `infrastructure` | 1 | 31 | 0 | 1.12KB | 0.0%  |
| `public` | 2 | 25 | 0 | 14.21KB | 0.0%  |
| `public` | 3 | 25 | 0 | 218.96KB | 0.0%  |
| `workflows` | 1 | 0 | 0 | 0.00B | 0.0%  |
| `docker` | 2 | 0 | 0 | 0.00B | 0.0%  |
| **TOTAL** | — | **80118** | — | **936.89MB** | 100% |

## Desglose por Extensión

| Extensión | Archivos | LoC | Peso |
| :--- | ---: | ---: | ---: |
| `.php` | 249 | 25229 | 1.15MB |
| `.tsx` | 108 | 23610 | 917.60KB |
| `.json` | 16 | 18904 | 646.49KB |
| `.lock` | 1 | 8729 | 314.54KB |
| `.ts` | 33 | 1854 | 65.65KB |
| `.py` | 1 | 435 | 23.00KB |
| `.md` | 5 | 280 | 14.25KB |
| `.css` | 3 | 264 | 9.96KB |
| `(sin extensión)` | 16 | 211 | 6.12KB |
| `.conf` | 4 | 131 | 3.86KB |
| `.yml` | 1 | 111 | 4.58KB |
| `.example` | 3 | 80 | 2.72KB |
| `.ps1` | 1 | 60 | 2.30KB |
| `.svg` | 4 | 50 | 28.42KB |
| `.xml` | 1 | 41 | 1.57KB |
| `.sh` | 1 | 41 | 1.58KB |
| `.js` | 4 | 35 | 1.64KB |
| `.html` | 2 | 26 | 761.00B |
| `.docker` | 1 | 17 | 513.00B |
| `.ini` | 1 | 7 | 158.00B |
| `.txt` | 1 | 2 | 24.00B |
| `.cache` | 1 | 1 | 48.71KB |
| `.ico` | 1 | 0 | 0.00B |
| `.jpg` | 14 | 0 | 2.71MB |
| `.png` | 5 | 0 | 1.46MB |

## Top 10 Archivos por LoC

| Archivo | LoC | Comentarios | En blanco | Peso |
| :--- | ---: | ---: | ---: | ---: |
| `web-public/package-lock.json` | 10317 | 0 | 0 | 359.29KB |
| `backend/composer.lock` | 8729 | 0 | 0 | 314.54KB |
| `web-admin/package-lock.json` | 7558 | 0 | 0 | 264.03KB |
| `backend/app/Services/SolicitudReservaService.php` | 1231 | 0 | 189 | 56.74KB |
| `web-admin/src/pages/parametricas/CamposDeportivos.tsx` | 1107 | 53 | 79 | 48.39KB |
| `web-admin/src/components/reservas/DialogCrearReservaVentanilla.tsx` | 1064 | 13 | 87 | 43.95KB |
| `web-admin/src/components/reservas/PiscinaReservasTab.tsx` | 921 | 23 | 112 | 34.98KB |
| `web-admin/src/components/parametricas/CampoFormDialog.tsx` | 890 | 44 | 49 | 35.95KB |
| `web-admin/src/pages/usuarios/Funcionarios.tsx` | 881 | 13 | 45 | 35.07KB |
| `web-admin/src/components/data-table.tsx` | 873 | 3 | 5 | 28.76KB |

## Mapa de Arquitectura

```text
campos-deportivos-gad-beni/
├── .editorconfig (20 LoC | 516.00B)
├── .env.docker (17 LoC | 513.00B)
├── .gitattributes (11 LoC | 225.00B)
├── audit.py (435 LoC | 23.00KB) [157 comentarios]
├── docker-compose.yml (111 LoC | 4.58KB) [17 comentarios]
├── README.md (93 LoC | 4.74KB)
├── .github/ [0.00B]
│   └── workflows/ [0.00B]
│       └── .gitkeep (0 LoC | 0.00B)
├── backend/ [73.24MB]
│   ├── .editorconfig (14 LoC | 275.00B)
│   ├── .env.example (76 LoC | 2.53KB)
│   ├── .gitattributes (9 LoC | 186.00B)
│   ├── .npmrc (2 LoC | 31.00B)
│   ├── .phpunit.result.cache (1 LoC | 48.71KB)
│   ├── artisan (12 LoC | 425.00B)
│   ├── composer.json (93 LoC | 2.78KB)
│   ├── composer.lock (8729 LoC | 314.54KB)
│   ├── Dockerfile (68 LoC | 2.13KB)
│   ├── package.json (19 LoC | 461.00B)
│   ├── phpunit.xml (41 LoC | 1.57KB)
│   ├── pint.json (3 LoC | 28.00B)
│   ├── README.md (36 LoC | 3.61KB)
│   ├── vite.config.js (19 LoC | 632.00B) [4 comentarios]
│   ├── app/ [516.20KB]
│   │   ├── Console/ [31.01KB]
│   │   │   └── Commands/ [31.01KB]
│   │   │       ├── ExpirarSolicitudesVencidas.php (36 LoC | 1.29KB)
│   │   │       ├── MapearCamposSireb.php (322 LoC | 14.20KB)
│   │   │       ├── SincronizarTarifasSireb.php (307 LoC | 14.68KB)
│   │   │       └── VincularFuncionarioMamore.php (20 LoC | 851.00B)
│   │   ├── DTOs/ [13.94KB]
│   │   │   ├── CrearCampoDTO.php (53 LoC | 2.28KB)
│   │   │   ├── EstadoCobroDTO.php (23 LoC | 765.00B)
│   │   │   ├── FranjaSolicitadaDTO.php (32 LoC | 976.00B)
│   │   │   ├── HorarioAtencionDTO.php (10 LoC | 302.00B)
│   │   │   ├── PiscinaReservaDTO.php (33 LoC | 1.17KB)
│   │   │   ├── RespuestaCobroDTO.php (25 LoC | 951.00B)
│   │   │   ├── ServicioAdicionalDTO.php (24 LoC | 611.00B)
│   │   │   ├── SolicitudCobroDTO.php (18 LoC | 578.00B)
│   │   │   ├── SolicitudCreadaDTO.php (14 LoC | 404.00B)
│   │   │   ├── SolicitudFiltrosDTO.php (95 LoC | 3.42KB)
│   │   │   ├── SolicitudReservaDTO.php (45 LoC | 1.48KB)
│   │   │   └── WebhookPayloadDTO.php (32 LoC | 1.11KB)
│   │   ├── Enums/ [1.17KB]
│   │   │   ├── EstadoPiscinaReserva.php (10 LoC | 237.00B)
│   │   │   ├── EstadoSolicitudReserva.php (10 LoC | 239.00B)
│   │   │   ├── ModalidadReservaCampo.php (8 LoC | 315.00B)
│   │   │   └── TipoTarifaSireb.php (13 LoC | 405.00B)
│   │   ├── Events/ [467.00B]
│   │   │   └── SolicitudConfirmada.php (17 LoC | 467.00B)
│   │   ├── Exceptions/ [2.26KB]
│   │   │   ├── FranjaNoDisponibleException.php (29 LoC | 878.00B)
│   │   │   ├── RecaudacionesApiException.php (28 LoC | 878.00B)
│   │   │   └── ServicioDeCobroNoDisponibleException.php (19 LoC | 557.00B)
│   │   ├── Http/ [180.27KB]
│   │   │   ├── Controllers/ [140.72KB]
│   │   │   │   ├── AuthController.php (164 LoC | 7.19KB)
│   │   │   │   ├── Controller.php (6 LoC | 77.00B)
│   │   │   │   └── Api/ [133.46KB]
│   │   │   │       └── V1/ [133.46KB]
│   │   │   │           ├── AsignacionFuncionarioController.php (59 LoC | 2.05KB)
│   │   │   │           ├── CampoDeportivoController.php (147 LoC | 5.51KB)
│   │   │   │           ├── DashboardController.php (34 LoC | 948.00B)
│   │   │   │           ├── FuncionarioController.php (85 LoC | 2.78KB)
│   │   │   │           ├── HealthController.php (24 LoC | 795.00B)
│   │   │   │           ├── OcupacionController.php (126 LoC | 4.67KB)
│   │   │   │           ├── ReportesController.php (120 LoC | 4.33KB)
│   │   │   │           ├── RolController.php (18 LoC | 498.00B)
│   │   │   │           ├── TarifaCampoController.php (32 LoC | 1.03KB)
│   │   │   │           ├── TipoCampoController.php (101 LoC | 3.23KB)
│   │   │   │           ├── UbicacionController.php (23 LoC | 787.00B)
│   │   │   │           ├── WebhookRecaudacionesController.php (97 LoC | 3.94KB)
│   │   │   │           ├── Admin/ [78.40KB]
│   │   │   │           │   ├── ArancelVarianteController.php (315 LoC | 13.98KB)
│   │   │   │           │   ├── AsistenciaController.php (56 LoC | 1.72KB)
│   │   │   │           │   ├── CatalogoArancelarioController.php (102 LoC | 4.29KB)
│   │   │   │           │   ├── CatalogoSirebController.php (410 LoC | 18.18KB)
│   │   │   │           │   ├── PiscinaReservaController.php (275 LoC | 10.93KB)
│   │   │   │           │   ├── PiscinaTurnoController.php (269 LoC | 12.56KB)
│   │   │   │           │   ├── ReservasExportController.php (56 LoC | 1.94KB)
│   │   │   │           │   └── SolicitudReservaController.php (362 LoC | 14.79KB)
│   │   │   │           └── Public/ [24.57KB]
│   │   │   │               ├── CampoController.php (140 LoC | 5.55KB)
│   │   │   │               ├── ConsultaEstadoController.php (159 LoC | 6.85KB)
│   │   │   │               ├── DisponibilidadController.php (52 LoC | 2.37KB)
│   │   │   │               ├── PiscinaController.php (133 LoC | 5.20KB)
│   │   │   │               ├── SolicitudEstadoController.php (53 LoC | 2.29KB)
│   │   │   │               ├── SolicitudReservaController.php (45 LoC | 1.70KB)
│   │   │   │               └── TipoCampoController.php (22 LoC | 630.00B)
│   │   │   ├── Middleware/ [7.38KB]
│   │   │   │   ├── RoleMiddleware.php (59 LoC | 2.28KB)
│   │   │   │   └── VerificaTokenOAuth.php (126 LoC | 5.10KB)
│   │   │   ├── Requests/ [19.77KB]
│   │   │   │   ├── AnularLiquidacionRequest.php (24 LoC | 707.00B)
│   │   │   │   ├── StoreAsistenciaRequest.php (26 LoC | 732.00B)
│   │   │   │   ├── StoreCampoDeportivoRequest.php (120 LoC | 5.79KB)
│   │   │   │   ├── StoreFuncionarioRequest.php (67 LoC | 2.62KB)
│   │   │   │   ├── StoreRegularizacionRequest.php (40 LoC | 1.83KB)
│   │   │   │   ├── StoreSolicitudReservaRequest.php (97 LoC | 5.85KB)
│   │   │   │   ├── StoreTipoCampoRequest.php (34 LoC | 1.09KB)
│   │   │   │   └── Piscina/ [1.17KB]
│   │   │   │       └── StorePiscinaReservaRequest.php (32 LoC | 1.17KB)
│   │   │   └── Resources/ [12.40KB]
│   │   │       ├── AuditoriaResource.php (21 LoC | 679.00B)
│   │   │       ├── CampoPublicoResource.php (54 LoC | 2.43KB)
│   │   │       ├── DisponibilidadResource.php (31 LoC | 1017.00B)
│   │   │       ├── SolicitudEstadoResource.php (47 LoC | 2.12KB)
│   │   │       ├── SolicitudReservaAdminResource.php (97 LoC | 4.71KB)
│   │   │       └── SolicitudReservaResource.php (38 LoC | 1.48KB)
│   │   ├── Integrations/ [31.82KB]
│   │   │   └── Recaudaciones/ [31.82KB]
│   │   │       ├── RecaudacionesApiClient.php (415 LoC | 15.32KB)
│   │   │       ├── RecaudacionesApiClientInterface.php (62 LoC | 2.12KB)
│   │   │       └── RecaudacionesApiClientSimulado.php (232 LoC | 14.38KB)
│   │   ├── Jobs/ [22.17KB]
│   │   │   ├── ExpirarPiscinaReservaJob.php (95 LoC | 3.55KB)
│   │   │   ├── ExpirarSolicitudJob.php (132 LoC | 5.44KB)
│   │   │   ├── PollingPiscinaReservaJob.php (42 LoC | 1.56KB)
│   │   │   ├── PollingSolicitudJob.php (39 LoC | 1.50KB)
│   │   │   ├── ReintentarPiscinaReservaJob.php (104 LoC | 4.10KB)
│   │   │   └── ReintentarSolicitudJob.php (152 LoC | 6.02KB)
│   │   ├── Models/ [26.59KB]
│   │   │   ├── ArancelVariante.php (56 LoC | 1.54KB)
│   │   │   ├── AsignacionFuncionario.php (26 LoC | 814.00B)
│   │   │   ├── Auditoria.php (29 LoC | 820.00B)
│   │   │   ├── CampoDeportivo.php (87 LoC | 3.06KB)
│   │   │   ├── Funcionario.php (64 LoC | 1.89KB)
│   │   │   ├── HorarioAtencion.php (18 LoC | 588.00B)
│   │   │   ├── ParametroSistema.php (22 LoC | 719.00B)
│   │   │   ├── PiscinaReserva.php (51 LoC | 1.46KB)
│   │   │   ├── PiscinaReservaDetalle.php (68 LoC | 2.11KB)
│   │   │   ├── PiscinaTurno.php (43 LoC | 1.13KB)
│   │   │   ├── PiscinaTurnoPerfil.php (39 LoC | 996.00B)
│   │   │   ├── Reserva.php (51 LoC | 1.48KB)
│   │   │   ├── Rol.php (28 LoC | 754.00B)
│   │   │   ├── SolicitudReserva.php (43 LoC | 1.49KB)
│   │   │   ├── SolicitudReservaDetalle.php (53 LoC | 1.56KB)
│   │   │   ├── SolicitudReservaServicio.php (43 LoC | 1.22KB)
│   │   │   ├── TarifaCampo.php (107 LoC | 3.62KB)
│   │   │   ├── TipoCampo.php (18 LoC | 606.00B)
│   │   │   └── User.php (29 LoC | 888.00B)
│   │   ├── Providers/ [1.09KB]
│   │   │   └── AppServiceProvider.php (29 LoC | 1.09KB)
│   │   └── Services/ [205.42KB]
│   │       ├── AsignacionFuncionarioService.php (102 LoC | 3.86KB)
│   │       ├── AuditoriaService.php (48 LoC | 1.95KB)
│   │       ├── AuthService.php (34 LoC | 1.11KB)
│   │       ├── CampoDeportivoService.php (112 LoC | 4.82KB)
│   │       ├── CampoPublicoSirebPresenter.php (337 LoC | 14.41KB)
│   │       ├── CatalogoSirebService.php (202 LoC | 7.47KB)
│   │       ├── ConfirmacionCobroService.php (106 LoC | 4.30KB)
│   │       ├── DashboardService.php (134 LoC | 4.93KB)
│   │       ├── DisponibilidadService.php (195 LoC | 9.53KB)
│   │       ├── FuncionarioService.php (105 LoC | 3.83KB)
│   │       ├── MarcarAsistenciaService.php (125 LoC | 4.70KB)
│   │       ├── OcupacionService.php (456 LoC | 19.54KB)
│   │       ├── ReportesService.php (135 LoC | 5.58KB)
│   │       ├── SolicitudReservaService.php (1231 LoC | 56.74KB)
│   │       ├── TarifaCampoService.php (105 LoC | 3.88KB)
│   │       ├── TipoCampoService.php (67 LoC | 1.95KB)
│   │       ├── Piscina/ [40.11KB]
│   │       │   ├── PiscinaConfirmacionCobroService.php (83 LoC | 3.23KB)
│   │       │   ├── PiscinaDisponibilidadService.php (155 LoC | 6.82KB)
│   │       │   ├── PiscinaReservaService.php (457 LoC | 20.77KB)
│   │       │   ├── PiscinaTarifaResolver.php (77 LoC | 2.77KB)
│   │       │   └── PiscinaTurnoLoteService.php (154 LoC | 6.52KB)
│   │       └── Sireb/ [16.71KB]
│   │           ├── ClasificacionTarifa.php (28 LoC | 1.06KB)
│   │           ├── ClasificadorServicioSireb.php (111 LoC | 4.74KB)
│   │           ├── ClasificadorTarifaSireb.php (100 LoC | 4.62KB)
│   │           └── SirebLiquidacionService.php (133 LoC | 6.29KB)
│   ├── bootstrap/ [24.78KB]
│   │   ├── app.php (39 LoC | 1.71KB)
│   │   ├── providers.php (5 LoC | 89.00B)
│   │   └── cache/ [22.99KB]
│   │       ├── packages.php (51 LoC | 963.00B)
│   │       └── services.php (269 LoC | 22.05KB)
│   ├── config/ [50.48KB]
│   │   ├── app.php (104 LoC | 4.20KB)
│   │   ├── auth.php (104 LoC | 4.00KB)
│   │   ├── beni.php (58 LoC | 1.35KB)
│   │   ├── cache.php (115 LoC | 4.27KB)
│   │   ├── cors.php (14 LoC | 408.00B)
│   │   ├── database.php (162 LoC | 6.71KB)
│   │   ├── filesystems.php (68 LoC | 2.47KB)
│   │   ├── logging.php (125 LoC | 4.70KB)
│   │   ├── mail.php (100 LoC | 3.55KB)
│   │   ├── queue.php (110 LoC | 4.10KB)
│   │   ├── sanctum.php (74 LoC | 3.03KB)
│   │   ├── services.php (76 LoC | 3.47KB)
│   │   └── session.php (197 LoC | 8.23KB)
│   ├── database/ [76.97KB]
│   │   ├── factories/ [1.02KB]
│   │   │   └── UserFactory.php (40 LoC | 1.02KB)
│   │   ├── migrations/ [52.17KB]
│   │   │   ├── 0000_00_00_000000_create_extensions.php (19 LoC | 728.00B)
│   │   │   ├── 0001_01_01_000000_create_roles_table.php (21 LoC | 657.00B)
│   │   │   ├── 0001_01_01_000000_create_users_table.php (44 LoC | 1.44KB)
│   │   │   ├── 0001_01_01_000001_create_cache_table.php (31 LoC | 873.00B)
│   │   │   ├── 0001_01_01_000002_create_jobs_table.php (53 LoC | 1.84KB)
│   │   │   ├── 0001_01_01_000003_create_funcionarios_table.php (25 LoC | 935.00B)
│   │   │   ├── 2026_08_25_163014_create_personal_access_tokens_table.php (26 LoC | 915.00B)
│   │   │   ├── 2026_09_01_095149_create_tipos_campo_table.php (21 LoC | 691.00B)
│   │   │   ├── 2026_09_01_095503_create_campos_deportivos_table.php (26 LoC | 1.01KB)
│   │   │   ├── 2026_09_01_100244_create_horarios_atencion_table.php (23 LoC | 831.00B)
│   │   │   ├── 2026_09_01_100825_create_tarifas_campo_table.php (24 LoC | 993.00B)
│   │   │   ├── 2026_09_01_101135_create_asignaciones_funcionario_table.php (22 LoC | 851.00B)
│   │   │   ├── 2026_09_01_103137_create_solicitudes_reserva_table.php (29 LoC | 1.20KB)
│   │   │   ├── 2026_09_01_103551_create_solicitud_reserva_detalle_table.php (44 LoC | 1.82KB)
│   │   │   ├── 2026_09_01_104002_create_trigger_sync_estado_solicitud.php (31 LoC | 1.11KB)
│   │   │   ├── 2026_09_01_105149_add_no_solape_horario_to_solicitud_reserva_detalle.php (28 LoC | 890.00B)
│   │   │   ├── 2026_09_01_105608_create_reservas_table.php (31 LoC | 1.29KB)
│   │   │   ├── 2026_09_01_112755_create_parametros_sistema_table.php (23 LoC | 826.00B)
│   │   │   ├── 2026_09_01_113512_create_auditoria_table.php (27 LoC | 1.04KB)
│   │   │   ├── 2026_09_01_142141_add_performance_indexes.php (48 LoC | 2.30KB)
│   │   │   ├── 2026_09_01_193247_add_unique_active_tarifa_index.php (22 LoC | 692.00B)
│   │   │   ├── 2026_09_16_120000_add_monto_confirmado_to_solicitudes_reserva.php (21 LoC | 614.00B)
│   │   │   ├── 2026_09_16_175420_add_datos_cobro_pendiente_to_solicitudes_reserva_table.php (19 LoC | 594.00B)
│   │   │   ├── 2026_09_23_100757_add_mamore_id_to_funcionarios_table.php (22 LoC | 600.00B)
│   │   │   ├── 2026_09_25_120000_add_imagen_url_to_campos_deportivos_table.php (19 LoC | 559.00B)
│   │   │   ├── 2026_09_25_180000_add_tipo_tarifa_to_tarifas_campo_table.php (51 LoC | 2.41KB)
│   │   │   ├── 2026_09_25_180001_add_hora_inicio_noche_to_campos_deportivos_table.php (21 LoC | 614.00B)
│   │   │   ├── 2026_10_01_000001_add_servicio_sireb_id_to_campos_deportivos.php (22 LoC | 695.00B)
│   │   │   ├── 2026_10_01_000002_add_sireb_fields_to_solicitudes_reserva.php (26 LoC | 1021.00B)
│   │   │   ├── 2026_10_01_000003_make_creado_por_nullable_in_tarifas_campo.php (18 LoC | 542.00B)
│   │   │   ├── 2026_10_03_000001_add_asistencia_to_reservas_table.php (27 LoC | 845.00B)
│   │   │   ├── 2026_10_03_000002_add_unique_index_servicio_sireb_id_to_campos_deportivos.php (44 LoC | 1.75KB)
│   │   │   ├── 2026_10_03_000003_add_servicio_sireb_codigo_to_campos_deportivos.php (28 LoC | 1.01KB)
│   │   │   ├── 2026_10_13_120000_add_ip_cobrador_to_solicitudes_reserva.php (23 LoC | 843.00B)
│   │   │   ├── 2026_10_14_000001_add_modalidad_reserva_to_campos_deportivos_table.php (21 LoC | 689.00B)
│   │   │   ├── 2026_10_14_000002_create_piscina_turnos_table.php (28 LoC | 1.04KB)
│   │   │   ├── 2026_10_14_000003_create_piscina_turno_perfiles_table.php (28 LoC | 1.09KB)
│   │   │   ├── 2026_10_14_000004_create_piscina_reservas_table.php (35 LoC | 1.49KB)
│   │   │   ├── 2026_10_14_000005_create_piscina_reserva_detalles_table.php (32 LoC | 1.27KB)
│   │   │   ├── 2026_10_15_000000_espejo_sireb_crudo_en_tarifas_campo.php (70 LoC | 4.54KB)
│   │   │   ├── 2026_10_15_000001_tipo_tarifa_nullable_en_tarifas_campo.php (32 LoC | 1.41KB)
│   │   │   ├── 2026_10_20_000001_create_arancel_variantes_table.php (37 LoC | 1.54KB)
│   │   │   ├── 2026_10_20_000002_add_variant_columns_to_solicitud_reserva_detalle_table.php (40 LoC | 1.58KB)
│   │   │   ├── 2026_10_21_000001_create_solicitud_reserva_servicio_table.php (43 LoC | 1.63KB)
│   │   │   ├── 2026_10_22_000001_add_ubicacion_a_campos_deportivos.php (22 LoC | 782.00B)
│   │   │   └── 2026_10_27_000001_add_confirmado_en_to_piscina_reservas.php (19 LoC | 541.00B)
│   │   ├── schema/ [0.00B]
│   │   └── seeders/ [23.78KB]
│   │       ├── ArancelVarianteCatalogoCompletoSeeder.php (121 LoC | 5.50KB)
│   │       ├── ArancelVariantePilotoSeeder.php (144 LoC | 5.73KB)
│   │       ├── DatabaseSeeder.php (16 LoC | 494.00B)
│   │       ├── FuncionarioSeeder.php (42 LoC | 1.55KB)
│   │       ├── MapeoSirebSeeder.php (42 LoC | 1.73KB)
│   │       ├── ParametrosSistemaSeeder.php (53 LoC | 2.00KB)
│   │       ├── PiscinaExampleSeeder.php (29 LoC | 1012.00B)
│   │       ├── RolesSeeder.php (32 LoC | 1.18KB)
│   │       ├── TipoCampoSeeder.php (43 LoC | 2.00KB)
│   │       └── UbicacionCampoSeeder.php (59 LoC | 2.62KB)
│   ├── docker/ [3.90KB]
│   │   ├── entrypoint.sh (41 LoC | 1.58KB)
│   │   ├── nginx.conf (35 LoC | 1.09KB)
│   │   ├── php.ini (7 LoC | 158.00B)
│   │   └── supervisord.conf (43 LoC | 1.09KB)
│   ├── public/ [1.98MB]
│   │   ├── .htaccess (20 LoC | 740.00B)
│   │   ├── favicon.ico (0 LoC | 0.00B)
│   │   ├── index.php (14 LoC | 543.00B)
│   │   ├── robots.txt (2 LoC | 24.00B)
│   │   └── storage/ [1.98MB]
│   │       └── campos/ [1.98MB]
│   │           ├── 7BkP89TjI9XsGo0yQOspBztS6nLQvqvGPRbJVmQB.jpg (0 LoC | 70.74KB)
│   │           ├── Dbsx2ACc7OEoFhxLiW3Cc3enmKwP2wF8vBp6YMpl.jpg (0 LoC | 30.90KB)
│   │           ├── iPDCYzI1Axk0RaOdoaDleOe6QoYGFI5LmFBCRtBc.jpg (0 LoC | 19.82KB)
│   │           ├── k7tvxII4JLAaDhCUq17RzXWzxjHGhG0pTsJ97tAT.png (0 LoC | 524.09KB)
│   │           ├── mzDhcm5W1LvGbpBpZ8BZn2UIppPuN0RZuthJi7cX.jpg (0 LoC | 30.90KB)
│   │           ├── Ovx52uWlSZUgehOgIT1ppESeht3GEu3BWPV1fTnA.jpg (0 LoC | 27.50KB)
│   │           ├── ThHmW6tJ2PhN5fr4DrMYB97u5LLqvFdyZZPDbSaO.jpg (0 LoC | 230.83KB)
│   │           ├── TJsSzO9KNzzOAs5RJSmxTaEU3NdmFnmQJNFCfF1n.jpg (0 LoC | 976.64KB)
│   │           └── XVSE9Yf1BsdToHPxjFbaitxzAbMyxhHgwVqkyEjy.png (0 LoC | 118.85KB)
│   ├── resources/ [70.96KB]
│   │   ├── css/ [355.00B]
│   │   │   └── app.css (1 LoC | 355.00B) [8 comentarios]
│   │   ├── js/ [3.00B]
│   │   │   └── app.js (0 LoC | 3.00B) [1 comentarios]
│   │   └── views/ [70.61KB]
│   │       └── welcome.blade.php (212 LoC | 70.61KB)
│   ├── routes/ [18.07KB]
│   │   ├── api.php (268 LoC | 17.09KB)
│   │   ├── console.php (9 LoC | 403.00B)
│   │   └── web.php (16 LoC | 595.00B)
│   ├── storage/ [2.06MB]
│   │   ├── app/ [1.98MB]
│   │   │   ├── private/ [0.00B]
│   │   │   └── public/ [1.98MB]
│   │   │       └── campos/ [1.98MB]
│   │   │           ├── 7BkP89TjI9XsGo0yQOspBztS6nLQvqvGPRbJVmQB.jpg (0 LoC | 70.74KB)
│   │   │           ├── Dbsx2ACc7OEoFhxLiW3Cc3enmKwP2wF8vBp6YMpl.jpg (0 LoC | 30.90KB)
│   │   │           ├── iPDCYzI1Axk0RaOdoaDleOe6QoYGFI5LmFBCRtBc.jpg (0 LoC | 19.82KB)
│   │   │           ├── k7tvxII4JLAaDhCUq17RzXWzxjHGhG0pTsJ97tAT.png (0 LoC | 524.09KB)
│   │   │           ├── mzDhcm5W1LvGbpBpZ8BZn2UIppPuN0RZuthJi7cX.jpg (0 LoC | 30.90KB)
│   │   │           ├── Ovx52uWlSZUgehOgIT1ppESeht3GEu3BWPV1fTnA.jpg (0 LoC | 27.50KB)
│   │   │           ├── ThHmW6tJ2PhN5fr4DrMYB97u5LLqvFdyZZPDbSaO.jpg (0 LoC | 230.83KB)
│   │   │           ├── TJsSzO9KNzzOAs5RJSmxTaEU3NdmFnmQJNFCfF1n.jpg (0 LoC | 976.64KB)
│   │   │           └── XVSE9Yf1BsdToHPxjFbaitxzAbMyxhHgwVqkyEjy.png (0 LoC | 118.85KB)
│   │   ├── framework/ [78.12KB]
│   │   │   ├── cache/ [0.00B]
│   │   │   │   └── data/ [0.00B]
│   │   │   ├── sessions/ [0.00B]
│   │   │   ├── testing/ [0.00B]
│   │   │   └── views/ [78.12KB]
│   │   │       ├── 2e034aa487dd73123d73c39b20cfb4d1.php (4 LoC | 449.00B)
│   │   │       ├── 6ecd89cf74a0cdc90ee9184457c35747.php (31 LoC | 6.71KB)
│   │   │       └── 7ea5f0f9c5138d7341c4b3c5bbe09c14.php (211 LoC | 70.97KB)
│   │   └── logs/ [0.00B]
│   ├── tests/ [343.25KB]
│   │   ├── TestCase.php (27 LoC | 1.11KB)
│   │   ├── Feature/ [331.57KB]
│   │   │   ├── AuthOAuthIbareTest.php (127 LoC | 4.77KB)
│   │   │   ├── AuthTest.php (27 LoC | 909.00B)
│   │   │   ├── ExampleTest.php (15 LoC | 359.00B)
│   │   │   ├── ModelsTest.php (267 LoC | 10.26KB)
│   │   │   ├── Api/ [250.75KB]
│   │   │   │   └── V1/ [250.75KB]
│   │   │   │       ├── AsignacionFuncionarioTest.php (171 LoC | 7.40KB)
│   │   │   │       ├── CampoDeportivoTest.php (169 LoC | 6.38KB)
│   │   │   │       ├── DashboardTest.php (197 LoC | 8.07KB)
│   │   │   │       ├── FuncionarioTest.php (163 LoC | 6.23KB)
│   │   │   │       ├── HealthTest.php (19 LoC | 500.00B)
│   │   │   │       ├── OcupacionTest.php (566 LoC | 25.00KB)
│   │   │   │       ├── ReportesTest.php (571 LoC | 25.14KB)
│   │   │   │       ├── TarifaCampoTest.php (319 LoC | 14.27KB)
│   │   │   │       ├── TipoCampoTest.php (96 LoC | 3.39KB)
│   │   │   │       ├── WebhookRecaudacionesControllerTest.php (158 LoC | 6.30KB)
│   │   │   │       ├── Admin/ [94.93KB]
│   │   │   │       │   ├── ArancelVarianteControllerTest.php (146 LoC | 5.95KB)
│   │   │   │       │   ├── ArancelVarianteServicioAdicionalControllerTest.php (97 LoC | 3.90KB)
│   │   │   │       │   ├── AsistenciaReservaTest.php (315 LoC | 12.72KB)
│   │   │   │       │   ├── RegularizacionReservaTest.php (237 LoC | 10.70KB)
│   │   │   │       │   ├── ReservasExportTest.php (275 LoC | 11.57KB)
│   │   │   │       │   ├── SolicitudReservaAnulacionTest.php (124 LoC | 5.08KB)
│   │   │   │       │   ├── SolicitudReservaListadoTest.php (378 LoC | 15.95KB)
│   │   │   │       │   ├── SolicitudReservaVentanillaServicioAdicionalTest.php (401 LoC | 16.34KB)
│   │   │   │       │   ├── SolicitudReservaVentanillaTest.php (83 LoC | 3.88KB)
│   │   │   │       │   └── SolicitudReservaVentanillaVarianteTest.php (242 LoC | 8.83KB)
│   │   │   │       └── Public/ [53.16KB]
│   │   │   │           ├── AntiHoardingTest.php (310 LoC | 13.21KB)
│   │   │   │           ├── CampoControllerTest.php (126 LoC | 5.33KB)
│   │   │   │           ├── DatosCobroPendienteTest.php (105 LoC | 4.43KB)
│   │   │   │           ├── DisponibilidadControllerTest.php (306 LoC | 13.29KB)
│   │   │   │           ├── SolicitudEstadoControllerTest.php (83 LoC | 3.49KB)
│   │   │   │           ├── SolicitudReservaCobroTest.php (146 LoC | 6.55KB)
│   │   │   │           └── SolicitudReservaControllerTest.php (169 LoC | 6.85KB)
│   │   │   ├── Console/ [9.84KB]
│   │   │   │   ├── SincronizarTarifasCatalogoCompletoTest.php (105 LoC | 4.74KB)
│   │   │   │   └── SincronizarTarifasVariantesTest.php (119 LoC | 5.09KB)
│   │   │   ├── Jobs/ [13.87KB]
│   │   │   │   ├── ReintentarPiscinaReservaJobTest.php (135 LoC | 4.93KB)
│   │   │   │   └── SolicitudesJobsTest.php (219 LoC | 8.94KB)
│   │   │   ├── Piscina/ [21.64KB]
│   │   │   │   ├── PiscinaReservaCrearTest.php (207 LoC | 8.54KB)
│   │   │   │   └── PiscinaReservaTest.php (269 LoC | 13.11KB)
│   │   │   └── Services/ [19.20KB]
│   │   │       ├── ConfirmacionCobroServiceEventoTest.php (83 LoC | 3.18KB)
│   │   │       ├── ConfirmacionCobroServiceTest.php (133 LoC | 5.42KB)
│   │   │       └── SolicitudReservaVarianteServiceTest.php (277 LoC | 10.61KB)
│   │   ├── Fixtures/ [0.00B]
│   │   └── Unit/ [10.58KB]
│   │       ├── ClasificadorServicioSirebTest.php (132 LoC | 5.88KB)
│   │       ├── ClasificadorTarifaSirebTest.php (89 LoC | 4.46KB)
│   │       └── ExampleTest.php (13 LoC | 243.00B)
│   └── vendor/ [67.75MB | 10110 archivos | dependencias omitidas]
├── infrastructure/ [3.42KB]
│   ├── LOCAL_SETUP.md (31 LoC | 1.12KB)
│   ├── docker/ [0.00B]
│   │   ├── nginx/ [0.00B]
│   │   │   └── .gitkeep (0 LoC | 0.00B)
│   │   └── php/ [0.00B]
│   │       └── .gitkeep (0 LoC | 0.00B)
│   └── scripts/ [2.30KB]
│       └── smoke_concurrencia_reserva.ps1 (60 LoC | 2.30KB)
├── web-admin/ [278.94MB]
│   ├── .env.example (3 LoC | 141.00B)
│   ├── .prettierignore (2 LoC | 18.00B)
│   ├── .prettierrc (7 LoC | 120.00B)
│   ├── components.json (25 LoC | 515.00B)
│   ├── Dockerfile (23 LoC | 777.00B)
│   ├── eslint.config.js (7 LoC | 454.00B) [8 comentarios]
│   ├── index.html (13 LoC | 393.00B)
│   ├── nginx.conf (29 LoC | 976.00B)
│   ├── package-lock.json (7558 LoC | 264.03KB)
│   ├── package.json (62 LoC | 1.62KB)
│   ├── README.md (60 LoC | 2.49KB)
│   ├── tsconfig.app.json (31 LoC | 819.00B)
│   ├── tsconfig.json (11 LoC | 234.00B)
│   ├── tsconfig.node.json (21 LoC | 558.00B)
│   ├── vite.config.ts (38 LoC | 1.02KB)
│   ├── dist/ [1.60MB | 15 archivos | dependencias omitidas]
│   ├── node_modules/ [276.34MB | 33539 archivos | dependencias omitidas]
│   ├── public/ [14.21KB]
│   │   ├── favicon.svg (1 LoC | 9.30KB)
│   │   └── icons.svg (24 LoC | 4.91KB)
│   └── src/ [733.07KB]
│       ├── App.tsx (60 LoC | 2.70KB)
│       ├── index.css (125 LoC | 4.70KB) [7 comentarios]
│       ├── main.tsx (21 LoC | 685.00B)
│       ├── app/ [12.50KB]
│       │   └── dashboard/ [12.50KB]
│       │       └── data.json (614 LoC | 12.50KB)
│       ├── components/ [406.47KB]
│       │   ├── .gitkeep (0 LoC | 0.00B)
│       │   ├── app-sidebar.tsx (206 LoC | 7.15KB) [4 comentarios]
│       │   ├── chart-area-interactive.tsx (291 LoC | 10.60KB)
│       │   ├── data-table.tsx (873 LoC | 28.76KB) [3 comentarios]
│       │   ├── nav-documents.tsx (86 LoC | 2.47KB)
│       │   ├── nav-main.tsx (56 LoC | 1.62KB)
│       │   ├── nav-secondary.tsx (36 LoC | 839.00B)
│       │   ├── nav-user.tsx (104 LoC | 3.21KB)
│       │   ├── RequireAuth.tsx (17 LoC | 584.00B)
│       │   ├── section-cards.tsx (109 LoC | 3.77KB)
│       │   ├── site-header.tsx (16 LoC | 683.00B)
│       │   ├── layout/ [3.88KB]
│       │   │   ├── AppLayout.tsx (87 LoC | 3.23KB) [3 comentarios]
│       │   │   └── ThemeProvider.tsx (17 LoC | 667.00B)
│       │   ├── parametricas/ [45.18KB]
│       │   │   ├── CampoFormDialog.tsx (890 LoC | 35.95KB) [44 comentarios]
│       │   │   └── VincularSirebDialog.tsx (238 LoC | 9.23KB) [4 comentarios]
│       │   ├── piscina/ [19.14KB]
│       │   │   ├── CrearTurnoDialog.tsx (310 LoC | 12.67KB) [11 comentarios]
│       │   │   └── CrearTurnosLoteDialog.tsx (156 LoC | 6.47KB) [2 comentarios]
│       │   ├── reservas/ [170.21KB]
│       │   │   ├── AnularLiquidacionDialog.tsx (113 LoC | 3.62KB)
│       │   │   ├── DialogCrearReservaPiscinaVentanilla.tsx (575 LoC | 24.31KB) [5 comentarios]
│       │   │   ├── DialogCrearReservaVentanilla.tsx (1064 LoC | 43.95KB) [13 comentarios]
│       │   │   ├── DialogDetalleReserva.tsx (833 LoC | 33.10KB) [12 comentarios]
│       │   │   ├── PiscinaReservasTab.tsx (921 LoC | 34.98KB) [23 comentarios]
│       │   │   ├── RegularizarUsoDialog.tsx (629 LoC | 26.60KB) [11 comentarios]
│       │   │   └── SnapshotAuditoria.tsx (102 LoC | 3.64KB) [5 comentarios]
│       │   └── ui/ [108.43KB]
│       │       ├── alert-dialog.tsx (190 LoC | 5.86KB) [5 comentarios]
│       │       ├── alert.tsx (53 LoC | 1.56KB)
│       │       ├── avatar.tsx (99 LoC | 2.95KB)
│       │       ├── badge.tsx (48 LoC | 1.88KB)
│       │       ├── breadcrumb.tsx (116 LoC | 2.53KB)
│       │       ├── button.tsx (76 LoC | 4.21KB) [10 comentarios]
│       │       ├── card.tsx (94 LoC | 2.57KB)
│       │       ├── chart.tsx (335 LoC | 10.25KB) [1 comentarios]
│       │       ├── checkbox.tsx (25 LoC | 1.50KB)
│       │       ├── dialog.tsx (146 LoC | 3.97KB)
│       │       ├── drawer.tsx (195 LoC | 11.09KB) [12 comentarios]
│       │       ├── dropdown-menu.tsx (268 LoC | 9.07KB) [6 comentarios]
│       │       ├── input.tsx (17 LoC | 1.02KB)
│       │       ├── label.tsx (15 LoC | 504.00B)
│       │       ├── select.tsx (205 LoC | 7.26KB) [7 comentarios]
│       │       ├── separator.tsx (20 LoC | 531.00B)
│       │       ├── sheet.tsx (124 LoC | 4.32KB)
│       │       ├── sidebar.tsx (665 LoC | 21.13KB) [9 comentarios]
│       │       ├── skeleton.tsx (11 LoC | 275.00B)
│       │       ├── sonner.tsx (45 LoC | 1.20KB)
│       │       ├── table.tsx (105 LoC | 2.35KB)
│       │       ├── tabs.tsx (73 LoC | 3.40KB)
│       │       ├── theme-toggle.tsx (44 LoC | 1.47KB)
│       │       ├── toggle-group.tsx (81 LoC | 3.09KB)
│       │       ├── toggle.tsx (39 LoC | 1.71KB)
│       │       └── tooltip.tsx (59 LoC | 2.78KB)
│       ├── context/ [4.28KB]
│       │   ├── AuthContext.tsx (72 LoC | 2.44KB) [5 comentarios]
│       │   └── BreadcrumbContext.tsx (59 LoC | 1.84KB)
│       ├── hooks/ [1.83KB]
│       │   ├── use-mobile.ts (15 LoC | 565.00B)
│       │   └── use-theme.ts (31 LoC | 1.28KB)
│       ├── lib/ [166.00B]
│       │   └── utils.ts (5 LoC | 166.00B)
│       ├── pages/ [245.76KB]
│       │   ├── auth/ [1.12KB]
│       │   │   └── Login.tsx (32 LoC | 1.12KB)
│       │   ├── Dashboard/ [7.47KB]
│       │   │   └── index.tsx (221 LoC | 7.47KB)
│       │   ├── ocupacion/ [32.84KB]
│       │   │   ├── MapaGlobal.tsx (223 LoC | 9.02KB) [2 comentarios]
│       │   │   └── MisCampos.tsx (539 LoC | 23.82KB) [10 comentarios]
│       │   ├── parametricas/ [101.98KB]
│       │   │   ├── CamposDeportivos.tsx (1107 LoC | 48.39KB) [53 comentarios]
│       │   │   ├── Tarifas.tsx (703 LoC | 29.27KB) [23 comentarios]
│       │   │   └── TiposCampo.tsx (655 LoC | 24.32KB) [11 comentarios]
│       │   ├── piscina/ [7.49KB]
│       │   │   └── Turnos.tsx (166 LoC | 7.49KB) [5 comentarios]
│       │   ├── reportes/ [15.04KB]
│       │   │   ├── ClientesFrecuentes.tsx (178 LoC | 6.35KB)
│       │   │   └── Dashboard.tsx (245 LoC | 8.68KB)
│       │   ├── Reservas/ [20.72KB]
│       │   │   └── index.tsx (623 LoC | 20.72KB) [5 comentarios]
│       │   └── usuarios/ [59.10KB]
│       │       ├── Asignaciones.tsx (579 LoC | 24.03KB) [12 comentarios]
│       │       └── Funcionarios.tsx (881 LoC | 35.07KB) [13 comentarios]
│       ├── services/ [34.61KB]
│       │   ├── apiClient.ts (20 LoC | 787.00B) [3 comentarios]
│       │   ├── asignacionesService.ts (33 LoC | 1.68KB) [17 comentarios]
│       │   ├── asistenciaService.ts (15 LoC | 628.00B) [9 comentarios]
│       │   ├── camposService.ts (125 LoC | 4.53KB) [27 comentarios]
│       │   ├── catalogoSirebService.ts (179 LoC | 5.46KB) [4 comentarios]
│       │   ├── dashboardService.ts (23 LoC | 607.00B)
│       │   ├── funcionariosService.ts (54 LoC | 2.10KB) [21 comentarios]
│       │   ├── ocupacionService.ts (38 LoC | 2.01KB) [25 comentarios]
│       │   ├── piscinaService.ts (117 LoC | 4.23KB) [1 comentarios]
│       │   ├── reportesService.ts (37 LoC | 1.24KB) [9 comentarios]
│       │   ├── solicitudesReservaService.ts (207 LoC | 8.45KB) [43 comentarios]
│       │   ├── tarifasService.ts (11 LoC | 969.00B) [16 comentarios]
│       │   └── tiposCampoService.ts (53 LoC | 2.00KB) [22 comentarios]
│       └── types/ [19.38KB]
│           ├── index.ts (11 LoC | 206.00B)
│           ├── mapaGlobal.ts (36 LoC | 926.00B)
│           ├── ocupacion.ts (78 LoC | 2.15KB) [5 comentarios]
│           ├── parametricas.ts (126 LoC | 6.79KB) [80 comentarios]
│           ├── piscina.ts (36 LoC | 956.00B) [1 comentarios]
│           ├── reportes.ts (45 LoC | 914.00B)
│           ├── reservas.ts (185 LoC | 4.73KB) [1 comentarios]
│           └── usuarios.ts (47 LoC | 2.78KB) [25 comentarios]
└── web-public/ [584.68MB]
    ├── .env.example (1 LoC | 51.00B)
    ├── components.json (25 LoC | 515.00B)
    ├── Dockerfile (23 LoC | 777.00B)
    ├── eslint.config.js (9 LoC | 591.00B) [12 comentarios]
    ├── index.html (13 LoC | 368.00B)
    ├── nginx.conf (24 LoC | 751.00B)
    ├── package-lock.json (10317 LoC | 359.29KB)
    ├── package.json (58 LoC | 1.56KB)
    ├── README.md (60 LoC | 2.29KB)
    ├── tsconfig.app.json (30 LoC | 779.00B)
    ├── tsconfig.json (7 LoC | 119.00B)
    ├── tsconfig.node.json (30 LoC | 794.00B)
    ├── vite.config.ts (23 LoC | 657.00B) [1 comentarios]
    ├── dist/ [1.46MB | 53 archivos | dependencias omitidas]
    ├── node_modules/ [582.38MB | 33394 archivos | dependencias omitidas]
    ├── public/ [218.96KB]
    │   ├── favicon.svg (1 LoC | 9.30KB)
    │   ├── icons.svg (24 LoC | 4.91KB)
    │   └── img/ [204.75KB]
    │       └── logo-gad-beni.png (0 LoC | 204.75KB)
    └── src/ [270.62KB]
        ├── index.css (138 LoC | 4.91KB) [4 comentarios]
        ├── main.tsx (13 LoC | 417.00B)
        ├── router.tsx (39 LoC | 1.74KB)
        ├── components/ [142.89KB]
        │   ├── campo/ [43.08KB]
        │   │   ├── BarraCarrito.tsx (48 LoC | 2.07KB)
        │   │   ├── CampoCard.tsx (182 LoC | 8.07KB) [3 comentarios]
        │   │   ├── FiltrosCampos.tsx (353 LoC | 12.94KB) [5 comentarios]
        │   │   ├── GrillaDisponibilidad.tsx (238 LoC | 8.65KB) [10 comentarios]
        │   │   ├── MapaCampos.tsx (151 LoC | 6.21KB) [1 comentarios]
        │   │   └── SelectorFechas.tsx (121 LoC | 5.14KB) [6 comentarios]
        │   ├── comprobante/ [17.34KB]
        │   │   ├── ComprobantePDF.tsx (238 LoC | 11.33KB) [11 comentarios]
        │   │   └── EstadoReserva.tsx (183 LoC | 6.01KB) [2 comentarios]
        │   ├── landing/ [28.47KB]
        │   │   ├── BandaCTA.tsx (35 LoC | 1.63KB)
        │   │   ├── CamposDestacados.tsx (236 LoC | 11.22KB) [8 comentarios]
        │   │   ├── ComoFunciona.tsx (70 LoC | 2.88KB)
        │   │   ├── Footer.tsx (109 LoC | 4.20KB)
        │   │   ├── Hero.tsx (89 LoC | 4.22KB)
        │   │   └── InfoInstitucional.tsx (108 LoC | 4.31KB) [6 comentarios]
        │   ├── layout/ [5.03KB]
        │   │   ├── Header.tsx (41 LoC | 1.81KB) [2 comentarios]
        │   │   ├── LayoutPublico.tsx (14 LoC | 355.00B)
        │   │   └── StepsReserva.tsx (72 LoC | 2.88KB) [2 comentarios]
        │   ├── pago/ [9.74KB]
        │   │   ├── CuentaRegresiva.tsx (81 LoC | 2.55KB) [1 comentarios]
        │   │   └── MedioDePago.tsx (201 LoC | 7.19KB) [5 comentarios]
        │   ├── reserva/ [10.92KB]
        │   │   ├── FormularioSolicitante.tsx (155 LoC | 5.50KB)
        │   │   └── ResumenCarrito.tsx (124 LoC | 5.42KB) [3 comentarios]
        │   └── ui/ [28.31KB]
        │       ├── badge.tsx (48 LoC | 1.87KB)
        │       ├── button.tsx (70 LoC | 3.78KB) [5 comentarios]
        │       ├── calendar.tsx (62 LoC | 2.64KB) [3 comentarios]
        │       ├── card.tsx (94 LoC | 2.56KB)
        │       ├── dialog.tsx (146 LoC | 3.96KB)
        │       ├── input.tsx (17 LoC | 1.01KB)
        │       ├── label.tsx (15 LoC | 494.00B)
        │       ├── popover.tsx (24 LoC | 1.20KB)
        │       ├── select.tsx (188 LoC | 6.49KB)
        │       └── sheet.tsx (125 LoC | 4.32KB)
        ├── lib/ [824.00B]
        │   ├── api.ts (20 LoC | 651.00B) [1 comentarios]
        │   └── utils.ts (5 LoC | 173.00B)
        ├── pages/ [112.68KB]
        │   ├── CampoDetalle.tsx (560 LoC | 23.11KB) [7 comentarios]
        │   ├── Campos.tsx (203 LoC | 9.12KB) [6 comentarios]
        │   ├── Comprobante.tsx (210 LoC | 8.68KB)
        │   ├── ComprobantePiscina.tsx (190 LoC | 7.63KB)
        │   ├── ConsultarEstado.tsx (299 LoC | 12.02KB) [10 comentarios]
        │   ├── Landing.tsx (34 LoC | 1.27KB)
        │   ├── Pago.tsx (576 LoC | 25.60KB) [11 comentarios]
        │   ├── PiscinaDetalle.tsx (391 LoC | 15.65KB) [3 comentarios]
        │   └── Reserva.tsx (216 LoC | 9.59KB) [2 comentarios]
        ├── services/ [841.00B]
        │   └── piscinaService.ts (22 LoC | 841.00B) [6 comentarios]
        ├── store/ [1011.00B]
        │   └── carritoStore.ts (32 LoC | 1011.00B)
        └── types/ [5.38KB]
            ├── campo.ts (87 LoC | 2.38KB) [8 comentarios]
            ├── piscina.ts (75 LoC | 1.83KB)
            └── reserva.ts (25 LoC | 1.17KB) [7 comentarios]
```
