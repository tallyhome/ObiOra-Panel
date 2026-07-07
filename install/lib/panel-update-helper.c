/*
 * ObiOra Panel — helper setuid root pour lancer update-panel.sh.
 * Linux ignore le bit setuid sur les scripts shell : seul un binaire ELF fonctionne.
 *
 * PIÈGE CRITIQUE : un binaire setuid a un UID réel (invoquant, ex. "obiora")
 * différent de son UID effectif (root, via le bit setuid). Si on exec() bash
 * directement sans setuid(0) préalable, bash détecte euid != uid et ABANDONNE
 * automatiquement ses privilèges root par sécurité (sauf option -p). Le script
 * update-panel.sh se retrouve alors avec EUID != 0 malgré le setuid — d'où
 * l'erreur "privilèges insuffisants" en boucle. On doit donc appeler setuid(0)
 * explicitement pour devenir root des DEUX côtés (réel ET effectif) avant exec.
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <sys/types.h>

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

    /* Devenir root de manière absolue (UID/GID réels ET effectifs = 0).
     * Sans ceci, bash détecte euid(0) != uid(obiora) et redescend seul en
     * utilisateur non privilégié avant même de lancer update-panel.sh. */
    if (setgid(0) != 0) {
        perror("setgid");
        return 1;
    }
    if (setuid(0) != 0) {
        perror("setuid");
        return 1;
    }

    if (geteuid() != 0 || getuid() != 0) {
        fprintf(stderr, "ERREUR: échec de l'élévation complète des privilèges\n");
        return 1;
    }

    exec_args[0] = "bash";
    exec_args[1] = script;
    exec_args[2] = history_id;
    exec_args[3] = NULL;

    execv("/bin/bash", exec_args);
    perror("execv");
    return 1;
}
