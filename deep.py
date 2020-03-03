class A:
    def __init__(self,b=None):
        self.b=b
        self.memo=None
    def deepcopy(self):
        if self.memo==None:
            newA=A()
            self.memo=newA
            newA.b=self.b.deepcopy()
            self.memo=None
            return newA
        else:
            return self.memo
class B:
    def __init__(self,a=None):
        self.a=a
    def deepcopy(self):
        newB=B()
        newB.a=self.a.deepcopy()
        return newB
