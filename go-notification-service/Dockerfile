FROM golang:1.27 AS build
WORKDIR /app
COPY go.mod go.sum ./
COPY . .
RUN go mod tidy && CGO_ENABLED=0 GOOS=linux go build -o notification-service .

FROM alpine:latest
RUN apk add --no-cache ca-certificates
WORKDIR /app
COPY --from=build /app/notification-service .
COPY --from=build /app/database/migration/sql ./database/migration/sql
EXPOSE 50051
CMD ["./notification-service"]