# Roadmap Obiora Doctor

Cette roadmap liste les fonctionnalites prevues pour transformer le prototype en plateforme de diagnostic professionnelle. Elle doit rester vivante et etre reorganisee a chaque jalon majeur.

## Phase 1 - Fondation

1. Creer la structure `bin/`, `core/`, `modules/`, `reports/`, `tests/`, `docs/`, `logs/` et `cache/`.
2. Definir le format standard d'un resultat de scan.
3. Definir le format standard d'un Health Score.
4. Definir les niveaux `INFO`, `WARNING` et `CRITICAL`.
5. Ajouter un moteur central d'execution des modules.
6. Ajouter un registre de modules.
7. Ajouter une configuration globale.
8. Ajouter une configuration par module.
9. Ajouter une detection de distribution Linux.
10. Ajouter une detection de version kernel.
11. Ajouter une detection des commandes disponibles.
12. Ajouter une couche d'abstraction pour les commandes shell.
13. Ajouter une gestion propre des erreurs.
14. Ajouter une gestion des timeouts par commande.
15. Ajouter une sortie terminal coloree.
16. Ajouter une sortie terminal sans couleur.
17. Ajouter une option `--json`.
18. Ajouter une option `--markdown`.
19. Ajouter une option `--html`.
20. Ajouter une option `--text`.
21. Ajouter une option `--quiet`.
22. Ajouter une option `--verbose`.
23. Ajouter une option `--debug`.
24. Ajouter une option `--no-color`.
25. Ajouter une option `--output`.
26. Ajouter une option `--profile`.
27. Ajouter une option `--module`.
28. Ajouter une option `--exclude-module`.
29. Ajouter une option `--list-modules`.
30. Ajouter une commande `obiora doctor scan`.

## Phase 2 - Rapports

31. Generer automatiquement `report.json`.
32. Generer automatiquement `report.md`.
33. Generer automatiquement `report.html`.
34. Generer automatiquement `report.txt`.
35. Creer un dossier horodate par execution.
36. Ajouter un index local des rapports.
37. Ajouter un resume global dans chaque rapport.
38. Ajouter un score par module dans chaque rapport.
39. Ajouter des recommandations par module.
40. Ajouter des liens internes dans le rapport HTML.
41. Ajouter un template HTML responsive.
42. Ajouter un mode sombre au rapport HTML.
43. Ajouter des badges de severite.
44. Ajouter un export anonymise.
45. Ajouter un export support client.
46. Ajouter un export compresse.
47. Ajouter une signature de rapport.
48. Ajouter les metadonnees host, kernel, OS et date.
49. Ajouter la duree d'execution totale.
50. Ajouter la duree d'execution par module.
51. Ajouter la version d'Obiora Doctor au rapport.
52. Ajouter un schema JSON versionne.
53. Ajouter une validation du JSON genere.
54. Ajouter une comparaison entre deux rapports.
55. Ajouter un diff de configuration.
56. Ajouter un diff de metriques.
57. Ajouter un diff de scores.
58. Ajouter une synthese des changements critiques.
59. Ajouter une commande `obiora doctor compare`.
60. Ajouter une commande `obiora doctor history`.

## Phase 3 - Modules Systeme

61. Creer le module CPU.
62. Detecter le modele CPU.
63. Detecter le nombre de sockets.
64. Detecter le nombre de coeurs.
65. Detecter les threads.
66. Detecter la frequence CPU.
67. Detecter le scaling governor.
68. Detecter la virtualisation CPU.
69. Detecter les flags CPU utiles.
70. Detecter les problemes de steal time.
71. Creer le module RAM.
72. Detecter la RAM totale.
73. Detecter la RAM disponible.
74. Detecter la pression memoire.
75. Detecter le swap actif.
76. Detecter le swappiness.
77. Detecter THP.
78. Detecter NUMA.
79. Detecter les erreurs ECC quand disponibles.
80. Detecter les OOM killers recents.
81. Creer le module disque.
82. Detecter les disques physiques.
83. Detecter les disques virtuels.
84. Detecter les partitions.
85. Detecter les filesystems.
86. Detecter l'espace libre.
87. Detecter les inodes libres.
88. Detecter les montages critiques.
89. Detecter les montages en lecture seule.
90. Detecter les erreurs disque kernel.
91. Creer le module SMART.
92. Detecter les disques compatibles SMART.
93. Lire les attributs SMART.
94. Detecter les secteurs reallocues.
95. Detecter les secteurs pending.
96. Detecter les erreurs CRC.
97. Detecter la temperature disque.
98. Detecter l'usure SSD/NVMe.
99. Detecter les alertes NVMe.
100. Generer une recommandation de remplacement disque.

## Phase 4 - Reseau et Kernel

101. Creer le module reseau.
102. Detecter les interfaces.
103. Detecter les IP publiques et privees.
104. Detecter les routes.
105. Detecter la passerelle.
106. Detecter les DNS.
107. Detecter les erreurs RX/TX.
108. Detecter les drops reseau.
109. Detecter la vitesse de lien.
110. Detecter le duplex.
111. Detecter les pilotes reseau.
112. Detecter les MTU incoherentes.
113. Tester la latence locale.
114. Tester la latence Internet.
115. Tester la resolution DNS.
116. Tester la connectivite HTTP.
117. Tester la connectivite HTTPS.
118. Tester la perte de paquets.
119. Tester les ports ecoutes.
120. Detecter les connexions anormales.
121. Creer le module kernel.
122. Detecter la version kernel.
123. Detecter les messages critiques `dmesg`.
124. Detecter les soft lockups.
125. Detecter les hard lockups.
126. Detecter les panics recents.
127. Detecter les warnings kernel.
128. Detecter les modules charges.
129. Detecter les parametres sysctl importants.
130. Detecter les limites systeme.

## Phase 5 - Virtualisation

131. Creer le module Virtualizor.
132. Detecter la version Virtualizor.
133. Detecter les services Virtualizor.
134. Detecter l'etat du panel.
135. Detecter les logs Virtualizor critiques.
136. Detecter les VPS actifs.
137. Detecter les VPS arretes.
138. Detecter les erreurs de creation VPS.
139. Detecter les erreurs de migration VPS.
140. Detecter les erreurs de stockage Virtualizor.
141. Detecter les bridges reseau Virtualizor.
142. Detecter les conflits d'IP.
143. Detecter les templates manquants.
144. Detecter les ISO manquantes.
145. Detecter les problemes de licence.
146. Creer le module KVM.
147. Detecter `libvirtd` ou `virtqemud`.
148. Detecter les domaines libvirt.
149. Detecter les erreurs QEMU.
150. Detecter les problemes de bridge KVM.
151. Creer le module LXC.
152. Detecter les conteneurs LXC.
153. Detecter les problemes AppArmor.
154. Detecter les problemes cgroups.
155. Detecter les limites conteneurs.
156. Creer le module Docker.
157. Detecter la version Docker.
158. Detecter l'etat du daemon Docker.
159. Detecter les conteneurs en erreur.
160. Detecter les redemarrages excessifs.
161. Detecter les images inutilisees.
162. Detecter les volumes orphelins.
163. Detecter les reseaux Docker.
164. Detecter les logs Docker volumineux.
165. Detecter les limites ressources Docker.

## Phase 6 - Services Web et Bases

166. Creer le module MySQL.
167. Creer le module MariaDB.
168. Creer le module PostgreSQL.
169. Detecter les versions de bases de donnees.
170. Detecter l'etat des services SQL.
171. Detecter les erreurs SQL recentes.
172. Detecter les connexions max atteintes.
173. Detecter les tables corrompues quand possible.
174. Detecter les requetes lentes.
175. Detecter les problemes InnoDB.
176. Creer le module Apache.
177. Creer le module Nginx.
178. Creer le module LiteSpeed.
179. Detecter les vhosts.
180. Detecter les erreurs web serveur.
181. Detecter les configurations SSL expirees.
182. Detecter les ports web.
183. Detecter les workers saturés.
184. Detecter les limites PHP-FPM.
185. Creer le module PHP.
186. Detecter les versions PHP installees.
187. Detecter les extensions PHP critiques.
188. Detecter les erreurs PHP recentes.
189. Creer le module Laravel.
190. Detecter les `.env` Laravel dangereux.
191. Detecter les caches Laravel.
192. Detecter les erreurs Laravel.
193. Creer le module cPanel.
194. Creer le module Plesk.
195. Creer le module DirectAdmin.

## Phase 7 - Securite, Benchmarks et Suite

196. Creer le module firewall.
197. Detecter firewalld.
198. Detecter UFW.
199. Detecter iptables.
200. Detecter nftables.
201. Detecter les ports publics sensibles.
202. Detecter SSH root login.
203. Detecter les cles SSH faibles.
204. Detecter Fail2ban.
205. Detecter SELinux.
206. Detecter AppArmor.
207. Detecter les paquets obsoletes.
208. Detecter les mises a jour securite disponibles.
209. Detecter les utilisateurs privilegies.
210. Detecter les permissions dangereuses.
211. Creer Obiora Bench CPU.
212. Creer Obiora Bench RAM.
213. Creer Obiora Bench disque.
214. Creer Obiora Bench IOPS.
215. Creer Obiora Bench reseau.
216. Creer Obiora Watch avec rafraichissement 1 seconde.
217. Ajouter l'historique en mode watch.
218. Ajouter une API REST locale.
219. Ajouter Obiora Agent.
220. Ajouter Obiora Monitor en dashboard web.
221. Ajouter un SDK de plugins.
222. Ajouter un store de plugins.
223. Ajouter une base de connaissances locale.
224. Ajouter les recommandations avec commandes de verification.
225. Ajouter un mode rescue avec confirmations.
226. Ajouter un mode backup.
227. Ajouter un mode deploy.
228. Ajouter un mode support anonymise.
229. Ajouter une politique de rollback.
230. Ajouter une suite de tests d'integration.
