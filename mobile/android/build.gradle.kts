allprojects {
    repositories {
        google()
        mavenCentral()
        // مرايا احتياطية: بعض الشبكات تحجب google()/mavenCentral() —
        // Gradle يجرّب بالترتيب ويأخذ أول استجابة ناجحة.
        maven("https://mirrors.cloud.tencent.com/maven")
        maven("https://repo.huaweicloud.com/repository/maven")
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
subprojects {
    project.evaluationDependsOn(":app")
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
