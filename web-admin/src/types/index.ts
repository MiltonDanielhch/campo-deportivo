export interface ApiResponse<T> {
  data: T;
  message?: string;
}

export interface HealthCheckResponse {
  status: string;
  service: string;
  version: string;
  timezone: string;
  timestamp: string;
}
