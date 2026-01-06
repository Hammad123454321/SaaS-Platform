import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

const publicRoutes = ["/", "/login", "/signup", "/reset-password", "/reset"];
const authRoutes = ["/login", "/signup"];

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  
  // Check for token in cookies or localStorage (via header)
  const token = request.cookies.get("access_token")?.value || 
                request.headers.get("authorization")?.replace("Bearer ", "");

  // Allow public routes
  if (publicRoutes.some(route => pathname.startsWith(route))) {
    // Redirect authenticated users away from auth pages
    if (authRoutes.some(route => pathname.startsWith(route)) && token) {
      return NextResponse.redirect(new URL("/dashboard", request.url));
    }
    return NextResponse.next();
  }

  // Protect all other routes - require authentication
  if (!token) {
    const loginUrl = new URL("/login", request.url);
    loginUrl.searchParams.set("redirect", pathname);
    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    /*
     * Match all request paths except for the ones starting with:
     * - api (API routes)
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - favicon.ico (favicon file)
     */
    "/((?!api|_next/static|_next/image|favicon.ico).*)",
  ],
};

