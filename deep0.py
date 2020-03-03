class A:
    def __init__(self,b=None):
        self.b=b
    def deepcopy(self):
        newA=A()
        newA.b=self.b.deepcopy()
        return newA
class B:
    def __init__(self,a=None):
        self.a=a
    def deepcopy(self):
        newB=B()
        newB.a=self.a.deepcopy()
        return newB
