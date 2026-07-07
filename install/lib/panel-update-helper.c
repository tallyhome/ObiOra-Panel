/*
 * ObiOra Panel — helper setuid root pour lancer update-panel.sh.
 * Linux ignore le bit setuid sur les scripts shell : seul un binaire ELF fonctionne.
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

#ifndef OBIORA_INSTALL_DIR
#define OBIORA_INSTALL_DIR "/opt/obiora-panel"
#endif

int main(int argc, char *argv[])
{
    char script[512];
    char history_id[32] = "0";
    char *exec_args[4];

    if (geteuid() != 0) {
        fprintf(stderr, "ERREUR: privilèges insuffisants pour la mise à jour\n");
        return 1;
    }

    snprintf(script, sizeof(script), "%s/install/update-panel.sh", OBIORA_INSTALL_DIR);

    if (access(script, X_OK) != 0) {
        fprintf(stderr, "ERREUR: script de mise à jour introuvable: %s\n", script);
        return 1;
    }

    if (argc > 1 && argv[1][0] != '\0') {
        strncpy(history_id, argv[1], sizeof(history_id) - 1);
        history_id[sizeof(history_id) - 1] = '\0';
    }

    exec_args[0] = "bash";
    exec_args[1] = script;
    exec_args[2] = history_id;
    exec_args[3] = NULL;

    execv("/bin/bash", exec_args);
    perror("execv");
    return 1;
}
