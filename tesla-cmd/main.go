package main

import (
	"bytes"
	"context"
	"fmt"
	"log"
	"net/http"
	"os/exec"
	"strconv"
	"strings"
	"sync"
	"time"
)

const (
	teslaBin = "/***/tesla-control"
	vin      = "LRWY*************"
	keyFile  = "/***/private_pkcs8.pem"
)

var mu sync.Mutex

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

	enableLog := r.URL.Query().Get("log") != ""
	cmdStr := r.URL.Query().Get("cmd")
	if cmdStr == "" {
		http.Error(w, "missing cmd", http.StatusBadRequest)
		return
	}

	timeoutSec := 5
	if t := r.URL.Query().Get("timeout"); t != "" {
		if parsed, err := strconv.Atoi(t); err == nil && parsed > 0 {
			timeoutSec = parsed
		}
	}

	for _, forbidden := range forbiddenCommands {
		if strings.HasPrefix(cmdStr, forbidden) {
			http.Error(w, "command forbidden for security", http.StatusForbidden)
			if enableLog {
				log.Printf("Blocked forbidden command: %s", cmdStr)
			}
			return
		}
	}

	mu.Lock()
	defer mu.Unlock()

	args := []string{
		"-ble",
		"-vin", vin,
		"-key-file", keyFile,
	}
	args = append(args, strings.Fields(cmdStr)...)

	ctx, cancel := context.WithTimeout(context.Background(), time.Duration(timeoutSec)*time.Second)
	defer cancel()

	cmd := exec.CommandContext(ctx, teslaBin, args...)

	var out bytes.Buffer
	cmd.Stdout = &out
	cmd.Stderr = &out

	if enableLog {
		log.Printf("Executing: %v", cmd.Args)
	}

	err := cmd.Run()

	w.Header().Set("Content-Type", "text/plain")

	if err != nil {
		if ctx.Err() == context.DeadlineExceeded {
			w.WriteHeader(http.StatusGatewayTimeout)
			w.Write([]byte(fmt.Sprintf("command timeout after %ds\n", timeoutSec)))
			return
		}
		w.WriteHeader(http.StatusInternalServerError)
		w.Write(out.Bytes())
		return
	}

	w.Write(out.Bytes())

	if enableLog {
		log.Printf("Output:\n%s", out.String())
	}
}
