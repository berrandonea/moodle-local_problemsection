# moodle-local_problem
Plug-in for Moodle, the well-known Learning Management System. 

Adds to the course a section where the teacher can submit a problem to groups of students and give them various collaboration tools to work together on a solution.

Author : Brice Errandonea <brice.errandonea@u-cergy.fr>

 Université de Cergy-Pontoise
 33, boulevard du Port
 95011 Cergy-Pontoise cedex
 FRANCE
 https://www.u-cergy.fr
 
Successfully tested on Moodle 2.9, 3.1, 3.2

A "problem section" is a special section in a Moodle course. It always contains an assignment, that a few student teams will take.
To work together, these students can be given various collaboration tools : a forum, a chat and/or a wiki (mod_etherpadlite and mod_publication are also supported if you have them on your Moodle site).

Of course, all of these tools already exist on Moodle without local_problemsection. What changes here is that you don't have to spend an hour to create the section, the grouping, the groups, the modules, set the modules to work with the grouping, and so on ... (many teachers would give up before ending this, anyway). No : a few clicks and it's done. And no risk to forget one of these steps.

Once this plugin is installed, a new item appears in your Course administration menu : Problem sections. Clicking this item opens a screen showing the problem sections available in the course, allowing you to manage them, create new ones, change the groups or jump to the submissions.

Creating a new problem section always generates a new grouping. But the groups in this grouping can be new ones (just choose how many you want) or they can be existing groups from another grouping. So, once your teams are set, you can give them several problem sections.
