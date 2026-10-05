# syntax=docker/dockerfile:1

# --- Tahap 1: build aset statis ---
FROM node:24-alpine AS build
WORKDIR /app

# Versi pnpm mengikuti field "packageManager" di package.json.
RUN corepack enable

# Salin file dependency dulu supaya layer install ter-cache selama dependency tidak berubah.
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .

# Variabel VITE_* ditanam ke bundle saat build (bukan saat container berjalan), jadi harus berupa build arg.
ARG VITE_API_URL=http://localhost:8000
ENV VITE_API_URL=$VITE_API_URL
RUN pnpm build

# --- Tahap 2: sajikan dengan nginx ---
FROM nginx:stable-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80