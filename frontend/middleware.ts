import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

const publicRoutes = ["/", "/login", "/signup", "/reset-password", "/reset", "/auth/verify-email", "/verify-email", "/accept-invite"];
const authRoutes = ["/login", "/signup"];
const onboardingRoutes = ["/onboarding"];

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  
  // Check for token in cookies or authorization header
  const token = request.cookies.get("access_token")?.value || 
                request.headers.get("authorization")?.replace("Bearer ", "");

  // Allow public routes
  if (publicRoutes.some(route => pathname.startsWith(route))) {
    // Redirect authenticated users away from auth pages
    // But respect redirect/verified query params for proper flow
    if (authRoutes.some(route => pathname.startsWith(route)) && token) {
      const redirectParam = request.nextUrl.searchParams.get("redirect");
      const verifiedParam = request.nextUrl.searchParams.get("verified");
      
      // If coming from dev mode signup, redirect to onboarding
      const destination = verifiedParam === "true" ? "/onboarding" : (redirectParam || "/dashboard");
      return NextResponse.redirect(new URL(destination, request.url));
    }
    return NextResponse.next();
  }

  // Allow onboarding route - it handles its own auth check
  if (onboardingRoutes.some(route => pathname.startsWith(route))) {
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

