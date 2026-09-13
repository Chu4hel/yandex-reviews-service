export interface User {
  id: number
  name: string
  email: string
  is_admin?: boolean
}

export interface LoginResponse {
  token: string
  user: User
}
