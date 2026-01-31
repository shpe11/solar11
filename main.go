package main

import (
	"bytes"
	"context"
	"log"
	"net/http"
	"os/exec"
	"strings"
	"sync"
	"time"
)

const (
	teslaBin = "/usr/local/bin/tesla-control"
	vin      = "***"
	keyFile  = "/etc/tesla/private_pkcs8.pem"
)

var mu sync.Mutex // mutex pentru a preveni exec paralel BLE

// Lista comenzi critice ce nu se permit prin HTTP
var forbiddenCommands = []string{
	"unlock",
	"door-unlock",
	"trunk-unlock",
	"frunk-unlock",
	"charge-start",
}

func main() {
	http.HandleFunc("/command", commandHandler)

	log.Println("Tesla HTTP daemon running on http://127.0.0.1:8080")
	log.Fatal(http.ListenAndServe("127.0.0.1:8080", nil))
}

func commandHandler(w http.ResponseWriter, r *http.Request) {
	cmdStr := r.URL.Query().Get("cmd")
	if cmdStr == "" {
		http.Error(w, "missing cmd", http.StatusBadRequest)
		return
	}

	// Verifica comenzi interzise
	for _, forbidden := range forbiddenCommands {
		if strings.HasPrefix(cmdStr, forbidden) {
			http.Error(w, "command forbidden for security", http.StatusForbidden)
			log.Printf("Blocked forbidden command: %s", cmdStr)
			return
		}
	}

	// Lock BLE
	mu.Lock()
	defer mu.Unlock()

	args := []string{
		"-ble",
		"-vin", vin,
		"-key-file", keyFile,
	}
	args = append(args, splitCmd(cmdStr)...)

	// Context cu timeout 5s
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	cmd := exec.CommandContext(ctx, teslaBin, args...)

	var out bytes.Buffer
	cmd.Stdout = &out
	cmd.Stderr = &out

	log.Printf("Executing command: %v", cmd.Args)

	err := cmd.Run()

	w.Header().Set("Content-Type", "text/plain")

	if err != nil {
		if ctx.Err() == context.DeadlineExceeded {
			w.WriteHeader(http.StatusGatewayTimeout)
			w.Write([]byte("command timeout after 5s\n"))
			log.Printf("Command timeout: %v", cmd.Args)
			return
		}

		w.WriteHeader(http.StatusInternalServerError)
		w.Write(out.Bytes())
		log.Printf("Command failed: %v\nOutput:\n%s", err, out.String())
		return
	}

	w.Write(out.Bytes())
	log.Printf("Command succeeded:\n%s", out.String())
}

func splitCmd(s string) []string {
	return strings.Fields(s)
}
